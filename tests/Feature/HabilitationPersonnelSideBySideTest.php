<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Socle\Contracts\HabilitationServiceContract;
use App\Modules\Socle\Filament\Pages\ManageHabilitations;
use Database\Seeders\HabilitationsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HabilitationPersonnelSideBySideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(HabilitationsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_manage_habilitations_removes_in_page_navigation_tabs_and_shows_governance_text(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        $response = $this->get(ManageHabilitations::getUrl());

        $response->assertSuccessful();

        // Technical Spatie text must NOT be present
        $response->assertDontSee('Les droits techniques définis par Spatie restent obligatoires');

        // Professional ERP text must be present
        $response->assertSee('Habilitation du personnel');
        $response->assertSee("Sélectionnez le profil d'habilitation du personnel", false);

        // Side-by-side panels headers
        $response->assertSee('Modules du système');
        $response->assertSee('Opérations — Administration');
    }

    public function test_manage_habilitations_displays_10_modules_and_4_standard_operations(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        $test = Livewire::test(ManageHabilitations::class);

        // Verify the 10 modules are displayed in the modules panel
        $modules = [
            'Administration',
            'Ressources humaines',
            'Scolarité',
            'Pédagogie',
            'Communication',
            'Finances',
            'Assiduité',
            'Vie scolaire',
            'Logistique',
            'Technique',
        ];

        foreach ($modules as $module) {
            $test->assertSee($module);
        }

        // Verify the 4 standard operations are displayed
        $test->assertSee('Consulter')
            ->assertSee('Modifier')
            ->assertSee('Exécuter')
            ->assertSee('Imprimer');
    }

    public function test_switching_module_updates_operations_panel(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        Livewire::test(ManageHabilitations::class)
            ->assertSet('moduleSelectionne', 'Administration')
            ->assertSee('Opérations — Administration')
            ->call('selectionnerModule', 'Ressources humaines')
            ->assertSet('moduleSelectionne', 'Ressources humaines')
            ->assertSee('Opérations — Ressources humaines')
            ->assertSee('rh.consulter')
            ->assertSee('rh.modifier')
            ->assertSee('rh.executer')
            ->assertSee('rh.imprimer');
    }

    public function test_switching_personnel_profile_highlights_active_profile(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        Livewire::test(ManageHabilitations::class)
            ->assertSet('roleSelectionne', 'Directeur')
            ->call('selectionnerRole', 'Enseignant')
            ->assertSet('roleSelectionne', 'Enseignant')
            ->assertSee('Enseignant');
    }

    public function test_deactivating_operation_requires_audit_motif_and_can_be_reactivated(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        $service = app(HabilitationServiceContract::class);

        // Ensure initially active for Directeur
        $service->activerModulePourRole('Directeur', 'socle.modifier');
        $this->assertTrue($service->moduleActifPourRole('Directeur', 'socle.modifier'));

        // Toggle off opens modal
        Livewire::test(ManageHabilitations::class)
            ->set('roleSelectionne', 'Directeur')
            ->call('selectionnerModule', 'Administration')
            ->call('demanderChangement', 'socle.modifier', true)
            ->assertDispatched('open-modal', id: 'motif-desactivation')
            ->set('motif', 'Restriction temporaire de maintenance')
            ->call('confirmerDesactivation')
            ->assertDispatched('close-modal', id: 'motif-desactivation')
            ->assertDispatched('platform-state-updated');

        $this->assertFalse($service->moduleActifPourRole('Directeur', 'socle.modifier'));

        // Toggle on activates directly
        Livewire::test(ManageHabilitations::class)
            ->set('roleSelectionne', 'Directeur')
            ->call('selectionnerModule', 'Administration')
            ->call('demanderChangement', 'socle.modifier', false)
            ->assertDispatched('platform-state-updated');

        $this->assertTrue($service->moduleActifPourRole('Directeur', 'socle.modifier'));
    }

    public function test_tout_autoriser_activates_all_four_operations(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        $service = app(HabilitationServiceContract::class);
        $service->desactiverModulePourRole('Enseignant', 'finances.modifier', 'Restriction test');
        $service->desactiverModulePourRole('Enseignant', 'finances.executer', 'Restriction test');

        Livewire::test(ManageHabilitations::class)
            ->set('roleSelectionne', 'Enseignant')
            ->call('selectionnerModule', 'Finances')
            ->call('toutAutoriserPourModule')
            ->assertDispatched('platform-state-updated');

        $this->assertTrue($service->moduleActifPourRole('Enseignant', 'finances.consulter'));
        $this->assertTrue($service->moduleActifPourRole('Enseignant', 'finances.modifier'));
        $this->assertTrue($service->moduleActifPourRole('Enseignant', 'finances.executer'));
        $this->assertTrue($service->moduleActifPourRole('Enseignant', 'finances.imprimer'));
    }
}
