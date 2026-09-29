<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Contracts\FraisScolaireServiceContract;
use App\Modules\Finances\Contracts\RepartitionPaiementServiceContract;
use App\Modules\Finances\Exceptions\VersementSuperieurAuDuException;
use App\Modules\Finances\Models\CatalogueFraisDivers;
use App\Modules\Finances\Models\FraisDiversEleve;
use App\Modules\Finances\Models\GroupeFrais;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Finances\Models\TypeFraisRecurrent;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\FormatNumerotation;
use App\Modules\Socle\Models\Niveau;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeEleveService;
use Tests\TestCase;

class FinancesPaymentAllocationTest extends TestCase
{
    use RefreshDatabase;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        $niveau = Niveau::query()->create(['nom' => 'Primaire', 'code' => 'PRI', 'ordre' => 1]);
        $this->annee = AnneeScolaire::query()->create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'statut' => 'active',
        ]);
        $groupe = GroupeFrais::query()->where('code', 'scolarite')->firstOrFail();
        TypeFraisRecurrent::query()->create([
            'nom' => 'Inscription', 'nature' => 'inscription', 'groupe_frais_id' => $groupe->id,
            'niveau_id' => $niveau->id, 'annee_scolaire_id' => $this->annee->id, 'montant' => 15000, 'actif' => true,
        ]);
        TypeFraisRecurrent::query()->create([
            'nom' => 'Scolarité', 'nature' => 'scolarite', 'groupe_frais_id' => $groupe->id,
            'niveau_id' => $niveau->id, 'annee_scolaire_id' => $this->annee->id, 'montant' => 100000,
            'ratio_tranche_1' => 50, 'actif' => true,
        ]);
        FormatNumerotation::query()->create([
            'type_document' => 'recu', 'format' => 'REC-{{annee}}-{{seq:4}}', 'prochain_numero' => 1,
        ]);
        $this->app->instance(EleveServiceInterface::class, new FakeEleveService([
            1 => ['nom' => 'Doe', 'prenom' => 'Jane', 'classe_id' => 7, 'niveau_id' => $niveau->id],
        ]));
        $inscriptions = $this->mock(InscriptionServiceInterface::class);
        $inscriptions->shouldReceive('getContexteFinancier')->with(9)->andReturn([
            'inscription_id' => 9, 'eleve_id' => 1, 'classe_id' => 7,
            'annee_scolaire_id' => $this->annee->id, 'statut' => 'en_attente_versement',
        ]);
        $this->actingAs(User::factory()->create(['statut' => 'actif']));
        app(CaisseServiceContract::class)->ouvrirSession(0);
    }

    public function test_payment_is_split_in_strict_priority_and_each_part_is_traceable(): void
    {
        $resultat = app(RepartitionPaiementServiceContract::class)->repartir(9, 25000, 'bancaire');

        $this->assertCount(2, $resultat['lignes']);
        $this->assertDatabaseHas('paiements', ['inscription_id' => 9, 'rubrique' => 'inscription', 'montant' => 15000]);
        $this->assertDatabaseHas('paiements', ['inscription_id' => 9, 'rubrique' => 'tranche_1', 'montant' => 10000]);
        $this->assertSame(1, Paiement::query()->distinct()->count('versement_reference'));

        $situation = app(FraisScolaireServiceContract::class)->getSituationDeuxTranches(1, $this->annee->id);
        $this->assertSame(0.0, $situation['inscription']['reste']);
        $this->assertSame(40000.0, $situation['tranche_1']['reste']);
        $this->assertSame('en_attente', $situation['tranche_2']['statut']);
    }

    public function test_excess_payment_is_refused_without_creating_a_line(): void
    {
        $this->expectException(VersementSuperieurAuDuException::class);

        try {
            app(RepartitionPaiementServiceContract::class)->repartir(9, 115001, 'bancaire');
        } finally {
            $this->assertDatabaseCount('paiements', 0);
        }
    }

    public function test_diverse_fee_is_paid_separately_and_never_enters_school_cascade(): void
    {
        $groupe = GroupeFrais::query()->where('code', 'autres')->firstOrFail();
        $poste = CatalogueFraisDivers::query()->create([
            'nom' => 'Cantine', 'categorie' => 'cantine', 'periodicite' => 'mensuelle',
            'groupe_frais_id' => $groupe->id, 'montant_defaut' => 20000, 'actif' => true,
        ]);
        $frais = FraisDiversEleve::query()->create([
            'catalogue_frais_divers_id' => $poste->id, 'eleve_id' => 1,
            'annee_scolaire_id' => $this->annee->id, 'montant' => 20000, 'statut' => 'actif',
        ]);

        app(RepartitionPaiementServiceContract::class)->payerFraisDivers($frais->id, 5000, 'bancaire');

        $this->assertDatabaseHas('paiements', [
            'frais_divers_eleve_id' => $frais->id, 'rubrique' => 'divers:'.$frais->id, 'montant' => 5000,
        ]);
        $situation = app(FraisScolaireServiceContract::class)->getSituationDeuxTranches(1, $this->annee->id);
        $this->assertSame(15000.0, $situation['inscription']['reste']);
        $this->assertSame(50000.0, $situation['tranche_1']['reste']);

        $this->expectException(VersementSuperieurAuDuException::class);
        app(RepartitionPaiementServiceContract::class)->payerFraisDivers($frais->id, 15001, 'bancaire');
    }
}
