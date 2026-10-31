<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\Scolarite\Filament\Resources\CategorieFraisResource\Pages\ListCategoriesFrais;
use App\Modules\Scolarite\Filament\Resources\ClasseResource\Pages\ListClasses;
use App\Modules\Scolarite\Filament\Resources\EleveResource\Pages\ListEleves;
use App\Modules\Scolarite\Filament\Resources\FactureResource\Pages\ListFactures;
use App\Modules\Scolarite\Filament\Resources\FraisResource\Pages\ListFrais;
use App\Modules\Scolarite\Filament\Resources\GrilleTarifaireResource\Pages\ListGrilleTarifaire;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages\ListInscriptions;
use App\Modules\Scolarite\Filament\Resources\PaiementResource\Pages\ListPaiements;
use App\Modules\Scolarite\Filament\Resources\ParentTuteurResource\Pages\ListParentTuteurs;
use App\Modules\Scolarite\Filament\Resources\TransfertAffectationResource\Pages\ListTransfertsAffectations;
use App\Modules\Scolarite\Filament\Resources\TypeDocumentEleveResource\Pages\ListTypesDocumentsEleve;
use App\Modules\Scolarite\Models\CategorieFrais;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Frais;
use App\Modules\Scolarite\Models\GrilleTarifaire;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Scolarite\Models\ParentTuteur;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScolariteModalsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create(['statut' => 'actif']);
        $this->admin->assignRole('Fondateur');
        $this->actingAs($this->admin);

        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'active',
        ]);
    }

    public function test_eleve_resource_has_golden_modal_actions(): void
    {
        $eleve = Eleve::create([
            'nom' => 'Kouam',
            'prenom' => 'Jean',
            'sexe' => 'M',
            'statut' => 'admis',
        ]);

        Livewire::test(ListEleves::class)
            ->assertSuccessful()
            ->assertActionExists('create')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('view')
            ->assertTableActionExists('delete');

        // Test modal creation
        Livewire::test(ListEleves::class)
            ->callAction('create', [
                'nom' => 'Mendouga',
                'prenom' => 'Claire',
                'sexe' => 'F',
            ]);

        $this->assertDatabaseHas('eleves', [
            'nom' => 'Mendouga',
            'prenom' => 'Claire',
            'sexe' => 'F',
        ]);

        // Test modal edit
        Livewire::test(ListEleves::class)
            ->callTableAction('edit', $eleve, [
                'nom' => 'Kouam Modifié',
                'prenom' => 'Jean',
                'sexe' => 'M',
                'statut' => 'admis',
                'nom_tuteur' => 'Kouam Papa',
                'telephone_tuteur' => '677112233',
            ]);

        $eleveActu = $eleve->fresh();
        $this->assertSame('Kouam Modifié', $eleveActu->nom);
        $this->assertSame('admis', $eleveActu->statut);
        $this->assertSame('Kouam Papa', $eleveActu->parentsTuteurs->first()?->nom);
        $this->assertSame('677112233', $eleveActu->parentsTuteurs->first()?->telephone);
    }

    public function test_parent_tuteur_resource_has_golden_modal_actions(): void
    {
        $parent = ParentTuteur::create([
            'nom' => 'Ngo',
            'prenom' => 'Marie',
            'telephone' => '699001122',
        ]);

        Livewire::test(ListParentTuteurs::class)
            ->assertSuccessful()
            ->assertActionExists('create')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('view')
            ->assertTableActionExists('delete');

        Livewire::test(ListParentTuteurs::class)
            ->callAction('create', [
                'nom' => 'Tchinda',
                'prenom' => 'Alain',
                'telephone' => '677889900',
            ]);

        $this->assertDatabaseHas('parents_tuteurs', [
            'nom' => 'Tchinda',
            'prenom' => 'Alain',
        ]);

        Livewire::test(ListParentTuteurs::class)
            ->callTableAction('edit', $parent, [
                'nom' => 'Ngo Modifiée',
                'prenom' => 'Marie',
                'telephone' => '699001122',
            ]);

        $this->assertSame('Ngo Modifiée', $parent->fresh()->nom);
    }

    public function test_classe_resource_modal_create_and_edit_with_configuration_service(): void
    {
        $employe = Employe::create([
            'matricule' => 'ENS-MODAL',
            'nom' => 'Prof',
            'prenom' => 'Alain',
            'poste' => 'Enseignant',
            'date_embauche' => today(),
            'statut' => 'actif',
        ]);
        $enseignant = Enseignant::create([
            'employe_id' => $employe->id,
            'specialite' => 'Maths',
            'statut_contractuel' => 'titulaire',
            'charge_horaire_hebdo' => 18,
        ]);
        $niveau = Niveau::create(['nom' => '6eme', 'code' => '6EME', 'ordre' => 6]);

        Livewire::test(ListClasses::class)
            ->assertSuccessful()
            ->assertActionExists('create')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('view')
            ->assertTableActionExists('delete');

        // Create via modal
        Livewire::test(ListClasses::class)
            ->callAction('create', [
                'nom' => '6eme A',
                'niveau_id' => $niveau->id,
                'annee_scolaire_id' => $this->annee->id,
                'capacite_max' => 35,
                'professeur_principal_id' => $enseignant->id,
                'statut' => 'active',
                'frais_inscription' => 15000,
                'tranche_1' => 20000,
                'tranche_2' => 20000,
                'tranche_3' => 20000,
                'tranche_4' => 20000,
                'tranche_5' => 20000,
            ]);

        $classe = Classe::where('nom', '6eme A')->firstOrFail();
        $this->assertSame(35, (int) $classe->capacite_max);
        $this->assertDatabaseHas('configurations_frais_classe', [
            'classe_id' => $classe->id,
            'frais_inscription' => 15000,
        ]);

        // Edit via modal
        Livewire::test(ListClasses::class)
            ->callTableAction('edit', $classe, [
                'nom' => '6eme A Modifié',
                'niveau_id' => $niveau->id,
                'annee_scolaire_id' => $this->annee->id,
                'capacite_max' => 40,
                'professeur_principal_id' => $enseignant->id,
                'statut' => 'active',
                'frais_inscription' => 15000,
                'tranche_1' => 25000,
                'tranche_2' => 25000,
                'tranche_3' => 25000,
                'tranche_4' => 25000,
                'tranche_5' => 25000,
            ]);

        $this->assertSame('6eme A Modifié', $classe->fresh()->nom);
        $this->assertSame(40, (int) $classe->fresh()->capacite_max);
    }

    public function test_categories_and_frais_modals(): void
    {
        Livewire::test(ListCategoriesFrais::class)
            ->assertSuccessful()
            ->assertActionExists('create')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('view')
            ->assertTableActionExists('delete');

        // Create categorie via modal
        Livewire::test(ListCategoriesFrais::class)
            ->callAction('create', ['nom' => 'Frais Scolaires 2026']);

        $categorie = CategorieFrais::where('nom', 'Frais Scolaires 2026')->firstOrFail();

        // Edit categorie via modal
        Livewire::test(ListCategoriesFrais::class)
            ->callTableAction('edit', $categorie, ['nom' => 'Frais Scolaires Maj']);

        $this->assertSame('Frais Scolaires Maj', $categorie->fresh()->nom);

        // Frais resource
        Livewire::test(ListFrais::class)
            ->assertSuccessful()
            ->assertActionExists('create')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('view')
            ->assertTableActionExists('delete');

        // Create frais via modal
        Livewire::test(ListFrais::class)
            ->callAction('create', [
                'nom' => 'Tranche 1',
                'categorie_frais_id' => $categorie->id,
                'utilise_grille_tarifaire' => true,
                'ordre_repartition' => 2,
            ]);

        $frais = Frais::where('nom', 'Tranche 1')->firstOrFail();
        $this->assertTrue((bool) $frais->utilise_grille_tarifaire);

        // Edit frais via modal
        Livewire::test(ListFrais::class)
            ->callTableAction('edit', $frais, [
                'nom' => 'Tranche 1 Première Partie',
                'categorie_frais_id' => $categorie->id,
                'utilise_grille_tarifaire' => true,
                'ordre_repartition' => 2,
            ]);

        $this->assertSame('Tranche 1 Première Partie', $frais->fresh()->nom);
    }

    public function test_grille_tarifaire_modals(): void
    {
        $cat = CategorieFrais::create(['nom' => 'Scolarite']);
        $frais = Frais::create([
            'nom' => 'Frais Inscription',
            'categorie_frais_id' => $cat->id,
            'utilise_grille_tarifaire' => true,
        ]);
        $niveau = Niveau::create(['nom' => 'Terminale', 'code' => 'TLE', 'ordre' => 12]);
        $classe = Classe::create([
            'nom' => 'Terminale C',
            'code' => 'TLE-C',
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
        ]);

        Livewire::test(ListGrilleTarifaire::class)
            ->assertSuccessful()
            ->assertActionExists('create')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('view')
            ->assertTableActionExists('delete');

        // Create tariff via modal
        Livewire::test(ListGrilleTarifaire::class)
            ->callAction('create', [
                'frais_id' => $frais->id,
                'classe_id' => $classe->id,
                'annee_scolaire_id' => $this->annee->id,
                'montant' => 50000,
            ]);

        $tarif = GrilleTarifaire::where('frais_id', $frais->id)
            ->where('classe_id', $classe->id)
            ->firstOrFail();
        $this->assertSame(50000.0, (float) $tarif->montant);

        // Edit tariff via modal
        Livewire::test(ListGrilleTarifaire::class)
            ->callTableAction('edit', $tarif, [
                'frais_id' => $frais->id,
                'classe_id' => $classe->id,
                'annee_scolaire_id' => $this->annee->id,
                'montant' => 60000,
            ]);

        $this->assertSame(60000.0, (float) $tarif->fresh()->montant);
    }

    public function test_all_remaining_scolarite_resources_render_modal_actions(): void
    {
        Livewire::test(ListInscriptions::class)
            ->assertSuccessful()
            ->assertActionExists('create')
            ->assertTableActionExists('view');

        Livewire::test(ListTransfertsAffectations::class)
            ->assertSuccessful()
            ->assertActionExists('create')
            ->assertTableActionExists('view');

        Livewire::test(ListFactures::class)
            ->assertSuccessful()
            ->assertTableActionExists('view')
            ->assertTableActionExists('imprimer');

        Livewire::test(ListPaiements::class)
            ->assertSuccessful()
            ->assertActionExists('creerVersement')
            ->assertTableActionExists('view');

        Livewire::test(ListTypesDocumentsEleve::class)
            ->assertSuccessful()
            ->assertActionExists('create')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('view')
            ->assertTableActionExists('delete');
    }
}
