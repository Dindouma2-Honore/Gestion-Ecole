<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Exceptions\MontantPaiementInvalideException;
use App\Modules\Finances\Exceptions\MotifAnnulationRequisException;
use App\Modules\Finances\Exceptions\ReferenceMobileMoneyRequiseException;
use App\Modules\Finances\Filament\Resources\PaiementResource;
use App\Modules\Finances\Filament\Resources\PaiementResource\Pages\CreatePaiement;
use App\Modules\Finances\Models\GrilleFrais;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Document;
use App\Modules\Socle\Models\FormatNumerotation;
use App\Modules\Socle\Models\Niveau;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LogicException;
use Tests\Support\FakeEleveService;
use Tests\TestCase;

class FinancesE40Test extends TestCase
{
    use RefreshDatabase;

    private PaiementServiceContract $paiements;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        $this->seed(RolesAndPermissionsSeeder::class);

        $niveau = Niveau::create(['nom' => 'CM2', 'code' => 'CM2', 'ordre' => 6]);
        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'active',
        ]);
        GrilleFrais::create([
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'type_frais' => 'scolarite',
            'montant' => 100000,
        ]);
        FormatNumerotation::create([
            'type_document' => 'recu',
            'format' => 'REC-{{annee}}-{{seq:4}}',
            'prochain_numero' => 1,
        ]);

        $this->app->instance(EleveServiceInterface::class, new FakeEleveService([
            1 => ['nom' => 'Doe', 'prenom' => 'Jane', 'classe_id' => 1, 'niveau_id' => $niveau->id],
        ]));

        $this->paiements = app(PaiementServiceContract::class);
        $this->actingAs(User::factory()->create(['statut' => 'actif']));
        app(CaisseServiceContract::class)->ouvrirSession(0);
    }

    public function test_enregistrer_paiement_generates_receipt_and_updates_balance(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($user);
        $paiement = $this->paiements->enregistrerPaiement(1, 35000, 'especes');

        $this->assertSame('REC-2026-2027-0001', $paiement->numero_recu);
        $this->assertSame($user->id, $paiement->encaisse_par);
        $this->assertNotNull($paiement->document_recu_id);
        $document = Document::findOrFail($paiement->document_recu_id);
        $this->assertSame('recu_paiement', $document->categorie);
        $this->assertSame('application/pdf', $document->mime_type);
        Storage::disk('documents')->assertExists($document->fichier_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('documents')->get($document->fichier_path));
        $this->assertSame(35000.0, $this->paiements->getTotalPaye(1, $this->annee->id));
        $this->assertSame(65000.0, $this->paiements->getResteAPayer(1, $this->annee->id));
    }

    public function test_montant_must_be_positive_and_mobile_money_requires_reference(): void
    {
        $this->actingAs(User::factory()->create(['statut' => 'actif']));

        try {
            $this->paiements->enregistrerPaiement(1, 0, 'especes');
            $this->fail('Le montant nul aurait dû être refusé.');
        } catch (MontantPaiementInvalideException) {
            $this->assertDatabaseCount('paiements', 0);
        }

        $this->expectException(ReferenceMobileMoneyRequiseException::class);
        $this->paiements->enregistrerPaiement(1, 10000, 'mobile_money');
    }

    public function test_annulation_requires_reason_is_audited_and_excluded_from_total(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($user);
        $paiement = $this->paiements->enregistrerPaiement(1, 25000, 'bancaire');

        try {
            $this->paiements->annulerPaiement($paiement->id, '   ');
            $this->fail('Le motif vide aurait dû être refusé.');
        } catch (MotifAnnulationRequisException) {
            $this->assertSame('valide', $paiement->fresh()->statut);
        }

        $this->paiements->annulerPaiement($paiement->id, 'Erreur de saisie');

        $this->assertSame('annule', $paiement->fresh()->statut);
        $this->assertSame(0.0, $this->paiements->getTotalPaye(1, $this->annee->id));
        $this->assertDatabaseHas('paiement_annulations', [
            'paiement_id' => $paiement->id,
            'annule_par' => $user->id,
            'motif' => 'Erreur de saisie',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Paiement::class,
            'subject_id' => $paiement->id,
            'causer_id' => $user->id,
        ]);

        $historique = $this->paiements->getHistoriquePaiements(1);
        $this->assertCount(1, $historique);
        $this->assertSame('annule', $historique->first()['statut']);
        $this->assertNotNull($historique->first()['url_recu']);
        $this->assertDatabaseHas('documents', [
            'id' => $paiement->fresh()->document_recu_id,
            'categorie' => 'recu_paiement',
        ]);
    }

    public function test_payment_cannot_be_physically_deleted(): void
    {
        $this->actingAs(User::factory()->create(['statut' => 'actif']));
        $paiement = $this->paiements->enregistrerPaiement(1, 10000, 'especes');

        $this->expectException(LogicException::class);
        $paiement->delete();
    }

    public function test_filament_creates_and_lists_payments_through_the_contract(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);

        Livewire::test(CreatePaiement::class)
            ->fillForm([
                'eleve_id' => 1,
                'montant' => 15000,
                'mode' => 'mobile_money',
                'reference_mobile_money' => 'MTN-12345',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('paiements', [
            'eleve_id' => 1,
            'reference_mobile_money' => 'MTN-12345',
            'statut' => 'valide',
        ]);
        $this->get(PaiementResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('Jane Doe');
    }
}
