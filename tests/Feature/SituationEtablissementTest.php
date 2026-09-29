<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\SituationEtablissement;
use App\Models\User;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Contracts\FraisScolaireServiceContract;
use App\Modules\Finances\Contracts\GestionDepenseServiceContract;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Models\GroupeFrais;
use App\Modules\Finances\Models\TypeFraisRecurrent;
use App\Modules\RH\Contracts\ContratServiceInterface;
use App\Modules\RH\Contracts\PaieServiceContract;
use App\Modules\RH\Contracts\PointageServiceContract;
use App\Modules\RH\Models\Employe;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\FormatNumerotation;
use App\Modules\Socle\Models\Niveau;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeEleveService;
use Tests\TestCase;

/**
 * Les 4 indicateurs de "Situation de l'établissement" viennent de deux
 * sources distinctes : Caisse (solde, dépenses du mois — déjà survenus) et
 * les modules d'origine (impayés élèves, salaires dus — pas encore réglés).
 * Ce test casserait si quelqu'un recalculait par erreur impayés/salaires
 * dus à partir des mouvements de Caisse.
 */
class SituationEtablissementTest extends TestCase
{
    use RefreshDatabase;

    private User $fondateur;

    private AnneeScolaire $annee;

    private Employe $employe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $niveau = Niveau::create(['nom' => 'CM2', 'code' => 'CM2', 'ordre' => 6]);
        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'statut' => 'active',
        ]);
        FormatNumerotation::create(['type_document' => 'recu', 'format' => 'REC-{{annee}}-{{seq:4}}', 'prochain_numero' => 1]);

        $groupeScolarite = GroupeFrais::query()->where('code', 'scolarite')->firstOrFail();
        TypeFraisRecurrent::query()->create([
            'nom' => 'Scolarité', 'groupe_frais_id' => $groupeScolarite->id, 'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $this->annee->id, 'montant' => 100000, 'actif' => true,
        ]);

        $this->app->instance(EleveServiceInterface::class, new FakeEleveService([
            1 => ['nom' => 'Doe', 'prenom' => 'Jane', 'classe_id' => 1, 'niveau_id' => $niveau->id],
            2 => ['nom' => 'Roe', 'prenom' => 'Jack', 'classe_id' => 1, 'niveau_id' => $niveau->id],
        ]));
        $this->app->instance(PointageServiceContract::class, new class implements PointageServiceContract
        {
            public function enregistrerPointage(int $employeId, \DateTimeInterface $dateHeure, string $type, string $modePointage, ?string $terminalId = null): void {}

            public function corrigerManuel(int $pointageId, ?string $heureArrivee, ?string $heureDepart, string $motif): void {}

            public function getJoursAbsenceNonJustifiee(int $employeId, int $mois, int $annee): int
            {
                return 0;
            }

            public function genererRapportMensuel(int $employeId, int $mois, int $annee): object
            {
                return (object) [];
            }
        });

        $this->fondateur = User::factory()->create(['statut' => 'actif']);
        $this->fondateur->assignRole('Fondateur');
        $this->actingAs($this->fondateur);

        app(CaisseServiceContract::class)->ouvrirSession(500000);

        // Eleve #1 paie une partie de sa scolarité, eleve #2 ne paie rien.
        app(PaiementServiceContract::class)->enregistrerPaiement(1, 30000, 'especes');

        $this->employe = Employe::create([
            'user_id' => $this->fondateur->id, 'matricule' => 'PAIE-002', 'nom' => 'Test',
            'prenom' => 'Salarié', 'date_embauche' => today()->subYear(), 'poste' => 'Agent', 'statut' => 'actif',
        ]);
        app(ContratServiceInterface::class)->creerContrat([
            'employe_id' => $this->employe->id, 'type' => 'CDI', 'categorie_paie' => 'fixe',
            'date_debut' => today()->subYear(), 'salaire_base' => 100000,
        ]);
    }

    public function test_the_four_indicators_come_from_the_right_source(): void
    {
        $depenses = app(GestionDepenseServiceContract::class);
        $rubrique = $depenses->creerRubrique('Fournitures');
        $depense = $depenses->creerDepense($rubrique->id, 'Manuels', 20000, 'Commande annuelle');
        $depenses->marquerPayee($depense->id);

        // Bulletin resté "calculé" : jamais décaissé, donc jamais en Caisse.
        $bulletin = app(PaieServiceContract::class)->calculerBulletin($this->employe->id, (int) now()->month, (int) now()->year);

        $this->assertSame(170000.0, app(FraisScolaireServiceContract::class)->getTotalImpayes($this->annee->id));
        $this->assertSame((float) $bulletin->net_a_payer, app(PaieServiceContract::class)->getSalairesDus((int) now()->month, (int) now()->year));
        $this->assertSame(500000.0 + 30000 - 20000, app(CaisseServiceContract::class)->getSoldeTheoriqueActuel());

        $response = $this->get(SituationEtablissement::getUrl())->assertSuccessful();
        $response->assertSee('510 000 FCFA', false);
        $response->assertSee('20 000 FCFA', false);
        $response->assertSee('170 000 FCFA', false);
        $response->assertSee(number_format((float) $bulletin->net_a_payer, 0, ',', ' ').' FCFA', false);
    }

    public function test_impayes_and_salaires_dus_are_not_recomputed_from_caisse_movements(): void
    {
        $bulletin = app(PaieServiceContract::class)->calculerBulletin($this->employe->id, (int) now()->month, (int) now()->year);

        $impayesAvant = app(FraisScolaireServiceContract::class)->getTotalImpayes($this->annee->id);
        $salairesDusAvant = app(PaieServiceContract::class)->getSalairesDus((int) now()->month, (int) now()->year);

        // Une dépense payée fait bouger la Caisse mais ne doit rien changer
        // aux impayés élèves ni aux salaires dus, qui viennent d'ailleurs.
        $depenses = app(GestionDepenseServiceContract::class);
        $rubrique = $depenses->creerRubrique('Fournitures');
        $depense = $depenses->creerDepense($rubrique->id, 'Manuels', 20000, 'Commande annuelle');
        $depenses->marquerPayee($depense->id);

        $this->assertSame($impayesAvant, app(FraisScolaireServiceContract::class)->getTotalImpayes($this->annee->id));
        $this->assertSame($salairesDusAvant, app(PaieServiceContract::class)->getSalairesDus((int) now()->month, (int) now()->year));
        $this->assertSame((float) $bulletin->net_a_payer, $salairesDusAvant);
    }
}
