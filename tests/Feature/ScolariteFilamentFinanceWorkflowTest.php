<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Filament\Pages\SituationFinanciere;
use App\Modules\Finances\Filament\Resources\PaiementResource;
use App\Modules\Finances\Filament\Resources\PaiementResource\Pages\CreatePaiement;
use App\Modules\Finances\Filament\Resources\PaiementResource\Pages\ListPaiements;
use App\Modules\Finances\Models\GrilleFrais;
use App\Modules\Finances\Models\MouvementCaisse;
use App\Modules\Finances\Models\Paiement as PaiementFinance;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Document;
use App\Modules\Socle\Models\FormatNumerotation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeEleveService;
use Tests\TestCase;

class ScolariteFilamentFinanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $comptable;

    private AnneeScolaire $annee;

    private Eleve $eleve;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->comptable = User::factory()->create(['statut' => 'actif']);
        $this->comptable->assignRole('Fondateur');
        $this->comptable->assignRole('Comptable');
        $this->actingAs($this->comptable->fresh());

        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'active',
        ]);
        $niveau = Niveau::create(['nom' => 'CM2', 'code' => 'CM2', 'ordre' => 6]);
        $classe = Classe::create([
            'nom' => 'CM2 A',
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
        ]);
        $this->eleve = Eleve::create([
            'matricule_permanent' => 'MAT-LOT19-001',
            'nom' => 'Doe',
            'prenom' => 'Jane',
            'sexe' => 'F',
            'date_naissance' => '2015-05-15',
            'statut' => 'inscrit',
        ]);
        Inscription::create([
            'eleve_id' => $this->eleve->id,
            'classe_id' => $classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'type' => 'inscription',
            'date_inscription' => today(),
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
            $this->eleve->id => [
                'id' => $this->eleve->id,
                'nom' => 'Doe',
                'prenom' => 'Jane',
                'classe_id' => $classe->id,
                'niveau_id' => $niveau->id,
                'telephone_parent' => '677000111',
            ],
        ]));
        app(CaisseServiceContract::class)->ouvrirSession(0);
    }

    public function test_paiement_partiel_et_complet_sont_encaissees_via_filament_et_restitues_dans_la_situation(): void
    {
        Livewire::test(CreatePaiement::class)
            ->assertSuccessful()
            ->fillForm(['eleve_id' => $this->eleve->id, 'montant' => 40000, 'mode' => 'especes'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $partiel = PaiementFinance::query()->where('eleve_id', $this->eleve->id)->firstOrFail();
        $this->assertSame(40000.0, (float) $partiel->montant);
        $this->assertSame('valide', $partiel->statut);
        $this->assertNotNull($partiel->document_recu_id);
        $this->assertDatabaseHas('mouvements_caisse', [
            'source_type' => PaiementFinance::class,
            'source_id' => $partiel->id,
            'type' => 'encaissement',
            'montant' => 40000,
        ]);
        $this->assertDatabaseHas('documents', ['id' => $partiel->document_recu_id, 'categorie' => 'recu_paiement']);
        Storage::disk('documents')->assertExists(Document::findOrFail($partiel->document_recu_id)->fichier_path);

        Livewire::test(SituationFinanciere::class)
            ->assertSuccessful()
            ->assertSee('Jane Doe')
            ->assertSee('100 000')
            ->assertSee('40 000')
            ->assertSee('60 000');

        Livewire::test(CreatePaiement::class)
            ->fillForm(['eleve_id' => $this->eleve->id, 'montant' => 60000, 'mode' => 'bancaire'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(100000.0, app(PaiementServiceContract::class)
            ->getTotalPaye($this->eleve->id, $this->annee->id));
        Livewire::test(SituationFinanciere::class)
            ->assertSee('Jane Doe')
            ->assertSee('100 000')
            ->assertSee('0');
    }

    public function test_annulation_et_double_annulation_sont_traitees_par_la_table_filament(): void
    {
        Livewire::test(CreatePaiement::class)
            ->fillForm(['eleve_id' => $this->eleve->id, 'montant' => 25000, 'mode' => 'bancaire'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();
        $paiement = PaiementFinance::query()->where('eleve_id', $this->eleve->id)->firstOrFail();
        $this->assertSame('valide', $paiement->statut);
        $this->assertSame($this->comptable->id, auth()->id());
        $this->assertTrue(auth()->user()->hasAnyRole(['Fondateur', 'Comptable']));
        $this->assertTrue($this->comptable->fresh()->hasAnyRole(['Fondateur', 'Comptable']));

        Livewire::test(ListPaiements::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$paiement])
            ->callTableAction('annuler', $paiement->id, ['motif' => 'Erreur de saisie'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('annule', $paiement->fresh()->statut);
        $this->assertDatabaseHas('paiement_annulations', [
            'paiement_id' => $paiement->id,
            'motif' => 'Erreur de saisie',
            'annule_par' => $this->comptable->id,
        ]);
        $this->assertSame(0.0, (float) MouvementCaisse::query()
            ->where('source_type', PaiementFinance::class)
            ->where('source_id', $paiement->id)
            ->where('type', 'encaissement')
            ->sum('montant') - 25000.0);

        Livewire::test(ListPaiements::class)
            ->callTableAction('annuler', $paiement, ['motif' => 'Deuxième tentative']);
        $this->assertDatabaseCount('paiement_annulations', 1);
        $this->assertDatabaseMissing('paiement_annulations', ['paiement_id' => $paiement->id, 'motif' => 'Deuxième tentative']);
    }

    public function test_page_de_caisse_et_recu_sont_accessibles_apres_encaissement(): void
    {
        Livewire::test(CreatePaiement::class)
            ->fillForm([
                'eleve_id' => $this->eleve->id,
                'montant' => 15000,
                'mode' => 'mobile_money',
                'reference_mobile_money' => 'MM-LOT19-001',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $paiement = PaiementFinance::query()->where('eleve_id', $this->eleve->id)->firstOrFail();
        Livewire::test(ListPaiements::class)
            ->assertCanSeeTableRecords([$paiement])
            ->assertTableActionExists('voir_recu');
        $this->assertSame('recu_paiement', Document::findOrFail($paiement->document_recu_id)->categorie);
        $this->get(PaiementResource::getUrl('index'))->assertSuccessful()->assertSee('Jane Doe');
    }
}
