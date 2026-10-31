<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\PosteAdministratif;
use App\Modules\Socle\Filament\Pages\OrganisationSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganisationHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_founder_can_open_organisation_hierarchy_screen(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif', 'must_change_password' => false]);
        $fondateur->assignRole('Fondateur');

        $this->actingAs($fondateur)
            ->get(OrganisationSettings::getUrl())
            ->assertSuccessful()
            ->assertSee('Organisation & Schéma Hiérarchique');
    }

    public function test_organigram_displays_functions_and_assigned_staff(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif', 'must_change_password' => false]);
        $fondateur->assignRole('Fondateur');

        $posteRacine = PosteAdministratif::create([
            'nom' => 'PROPRIETOR',
            'departement' => 'Gouvernance',
            'niveau_hierarchique' => 1,
            'ordre' => 1,
            'actif' => true,
        ]);

        $posteCoord = PosteAdministratif::create([
            'nom' => 'COORDINATOR',
            'parent_id' => $posteRacine->id,
            'departement' => 'Direction Générale',
            'niveau_hierarchique' => 2,
            'ordre' => 1,
            'actif' => true,
        ]);

        $employe = Employe::create([
            'matricule' => 'EMP-TEST-001',
            'nom' => 'NDAH',
            'prenom' => 'JOHN',
            'poste' => 'PROPRIETOR',
            'date_embauche' => '2026-09-01',
            'statut' => 'actif',
            'poste_administratif_id' => $posteRacine->id,
        ]);

        Livewire::actingAs($fondateur)
            ->test(OrganisationSettings::class)
            ->assertSuccessful()
            ->assertSee('PROPRIETOR')
            ->assertSee('COORDINATOR')
            ->assertSee('NDAH JOHN')
            ->assertSee('EMP-TEST-001');
    }

    public function test_organigram_is_strictly_read_only_with_no_create_or_edit_actions(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif', 'must_change_password' => false]);
        $fondateur->assignRole('Fondateur');

        $poste = PosteAdministratif::create([
            'nom' => 'PRINCIPAL',
            'departement' => 'Direction Pédagogique',
            'niveau_hierarchique' => 3,
            'actif' => true,
        ]);

        $component = Livewire::actingAs($fondateur)
            ->test(OrganisationSettings::class)
            ->assertSuccessful();

        // Vérifier l'absence formelle de boutons de mutation
        $component->assertDontSee('Nouvelle fonction')
            ->assertDontSee('+ Affecter du personnel')
            ->assertDontSee('Modifier la hiérarchie')
            ->assertDontSee('Créer la fonction');
    }

    public function test_organigram_can_filter_by_department_and_status(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif', 'must_change_password' => false]);
        $fondateur->assignRole('Fondateur');

        $poste1 = PosteAdministratif::create([
            'nom' => 'DIRECTION TECHNIQUE',
            'departement' => 'Technique',
            'niveau_hierarchique' => 2,
            'actif' => true,
        ]);

        $poste2 = PosteAdministratif::create([
            'nom' => 'SURVEILLANCE',
            'departement' => 'Vie Scolaire',
            'niveau_hierarchique' => 3,
            'actif' => true,
        ]);

        Employe::create([
            'matricule' => 'EMP-TEST-003',
            'nom' => 'TCHOUA',
            'prenom' => 'SERGE',
            'poste' => 'SURVEILLANCE',
            'date_embauche' => '2026-09-01',
            'statut' => 'actif',
            'poste_administratif_id' => $poste2->id,
        ]);

        $component = Livewire::actingAs($fondateur)
            ->test(OrganisationSettings::class)
            ->assertSee('DIRECTION TECHNIQUE')
            ->assertSee('SURVEILLANCE');

        // Filtrer par département "Technique"
        $component->set('selectedDepartement', 'Technique')
            ->assertSee('DIRECTION TECHNIQUE')
            ->assertDontSee('SURVEILLANCE');

        // Filtrer par postes vacants
        $component->set('selectedDepartement', 'all')
            ->set('selectedStatut', 'vacant')
            ->assertSee('DIRECTION TECHNIQUE')
            ->assertDontSee('TCHOUA SERGE');
    }

    public function test_can_toggle_between_tree_and_table_views(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif', 'must_change_password' => false]);
        $fondateur->assignRole('Fondateur');

        PosteAdministratif::create([
            'nom' => 'DIRECTION GENERALE',
            'departement' => 'Administration',
            'niveau_hierarchique' => 1,
            'actif' => true,
        ]);

        Livewire::actingAs($fondateur)
            ->test(OrganisationSettings::class)
            ->assertSet('viewMode', 'organigramme')
            ->set('viewMode', 'tableau')
            ->assertSet('viewMode', 'tableau')
            ->assertSee('Pôle / Département')
            ->assertSee('DIRECTION GENERALE');
    }
}
