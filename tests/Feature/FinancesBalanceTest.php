<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Contracts\BalanceServiceContract;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Contracts\GestionDepenseServiceContract;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Models\MouvementCaisse;
use App\Modules\RH\Contracts\ContratServiceInterface;
use App\Modules\RH\Contracts\PaieServiceContract;
use App\Modules\RH\Contracts\PointageServiceContract;
use App\Modules\RH\Models\Employe;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\FormatNumerotation;
use App\Modules\Socle\Models\Niveau;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeEleveService;
use Tests\TestCase;

/**
 * La Balance ne doit lire que les mouvements de Caisse (E41), déjà tagués
 * module_origine/sous_module par leurs points d'entrée respectifs — jamais
 * fusionner plusieurs sources, sous peine de double comptage.
 */
class FinancesBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $fondateur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $niveau = Niveau::create(['nom' => 'CM2', 'code' => 'CM2', 'ordre' => 6]);
        AnneeScolaire::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'statut' => 'active',
        ]);
        FormatNumerotation::create(['type_document' => 'recu', 'format' => 'REC-{{annee}}-{{seq:4}}', 'prochain_numero' => 1]);
        $this->app->instance(EleveServiceInterface::class, new FakeEleveService([
            1 => ['nom' => 'Doe', 'prenom' => 'Jane', 'classe_id' => 1, 'niveau_id' => $niveau->id],
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

        app(CaisseServiceContract::class)->ouvrirSession(0);
    }

    public function test_balance_reflects_every_cash_movement_exactly_once_with_no_double_counting(): void
    {
        // Scolarité — encaissement.
        $paiement = app(PaiementServiceContract::class)->enregistrerPaiement(1, 15000, 'especes');

        // Finances — décaissement d'une dépense payée.
        $depenses = app(GestionDepenseServiceContract::class);
        $rubrique = $depenses->creerRubrique('Fournitures');
        $depense = $depenses->creerDepense($rubrique->id, 'Manuels', 20000, 'Commande annuelle');
        $depenses->marquerPayee($depense->id);

        // RH — décaissement de salaire.
        $employe = Employe::create([
            'user_id' => $this->fondateur->id, 'matricule' => 'PAIE-001', 'nom' => 'Test',
            'prenom' => 'Salarié', 'date_embauche' => today()->subYear(), 'poste' => 'Agent', 'statut' => 'actif',
        ]);
        app(ContratServiceInterface::class)->creerContrat([
            'employe_id' => $employe->id, 'type' => 'CDI', 'categorie_paie' => 'fixe',
            'date_debut' => today()->subYear(), 'salaire_base' => 100000,
        ]);
        $paie = app(PaieServiceContract::class);
        $bulletin = $paie->calculerBulletin($employe->id, (int) now()->month, (int) now()->year);
        $paie->validerBulletin($bulletin->id, $this->fondateur->id);
        $paie->marquerPaye($bulletin->id, now());

        // RH — décaissement d'une avance sur salaire.
        $avance = $paie->demanderAvance($employe->id, 10000, 'Urgence familiale');
        $paie->validerAvance($avance->id, 'Accordée par le Fondateur');

        $totalMouvementsEnBase = MouvementCaisse::query()->count();
        $this->assertSame(4, $totalMouvementsEnBase, 'Les 4 opérations doivent avoir créé exactement 4 mouvements de Caisse.');

        $balance = app(BalanceServiceContract::class)->getBalance(
            CarbonImmutable::now()->subHour(),
            CarbonImmutable::now()->addHour(),
        );

        $this->assertSame($totalMouvementsEnBase, $balance->nombre_operations);
        $this->assertCount(1, $balance->detail_entrees);
        $this->assertCount(3, $balance->detail_sorties);

        $idsVus = $balance->detail_entrees->pluck('id')->merge($balance->detail_sorties->pluck('id'));
        $this->assertSame($idsVus->count(), $idsVus->unique()->count(), 'Un mouvement de Caisse ne doit jamais apparaître deux fois dans la Balance.');
        $this->assertSame(
            MouvementCaisse::query()->pluck('id')->sort()->values()->all(),
            $idsVus->sort()->values()->all(),
        );

        $this->assertSame(15000.0, $balance->total_entrees);
        $bulletinNetAPayer = (float) $bulletin->refresh()->net_a_payer;
        $this->assertSame(round(20000 + $bulletinNetAPayer + 10000, 2), $balance->total_sorties);
        $this->assertSame(round($balance->total_entrees - $balance->total_sorties, 2), $balance->solde_net);

        $this->assertArrayHasKey('Scolarité', $balance->par_module);
        $this->assertArrayHasKey('Finances', $balance->par_module);
        $this->assertArrayHasKey('RH', $balance->par_module);
        $this->assertSame(15000.0, $balance->par_module['Scolarité']['sous_modules']['Paiements élèves']['encaissements']);
        $this->assertSame(20000.0, $balance->par_module['Finances']['sous_modules']['Dépenses']['decaissements']);
        $this->assertSame($bulletinNetAPayer, $balance->par_module['RH']['sous_modules']['Salaires']['decaissements']);
        $this->assertSame(10000.0, $balance->par_module['RH']['sous_modules']['Avances']['decaissements']);
    }

    public function test_balance_filters_recalculate_totals_from_the_unique_cash_source(): void
    {
        app(PaiementServiceContract::class)->enregistrerPaiement(1, 15000, 'especes');
        $depenses = app(GestionDepenseServiceContract::class);
        $rubrique = $depenses->creerRubrique('Fournitures');
        $depense = $depenses->creerDepense($rubrique->id, 'Manuels', 20000, 'Commande annuelle');
        $depenses->marquerPayee($depense->id);

        $balance = app(BalanceServiceContract::class)->getBalance(
            CarbonImmutable::now()->subHour(),
            CarbonImmutable::now()->addHour(),
            'Scolarité',
            'encaissement',
        );

        $this->assertSame(15000.0, $balance->total_entrees);
        $this->assertSame(0.0, $balance->total_sorties);
        $this->assertSame(1, $balance->nombre_operations);
        $this->assertSame(['Scolarité'], array_keys($balance->par_module));
    }
}
