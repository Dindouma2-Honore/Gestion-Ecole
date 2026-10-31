<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Support\ModuleExperience;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleDashboardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_module_portal_links_to_every_dashboard(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');

        $this->actingAs($user)
            ->get('/accueil')
            ->assertSuccessful()
            ->assertSee('Bienvenue dans votre espace')
            ->assertSee('module-carousel', false)
            ->assertSee('card--blue', false)
            ->assertSee('class="icon"', false)
            ->assertSee('Navigation manuelle')
            ->assertSee('Administration')
            ->assertSee('Ressources humaines')
            ->assertSee('Scolarité')
            ->assertSee('/admin/scolarite', false)
            ->assertSee('Pédagogie')
            ->assertSee('Finances')
            ->assertSee('Assiduité')
            ->assertSeeInOrder([
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
            ]);

        $this->get('/admin')->assertRedirect('/accueil');
    }

    public function test_every_module_has_an_independent_dashboard_route(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);

        $this->get('/admin/socle')->assertSuccessful();

        foreach ([
            '/admin/rh',
            '/admin/pedagogie',
            '/admin/finances',
            '/admin/assiduite',
            '/admin/logistique',
            '/admin/vie-scolaire',
            '/admin/communication',
            '/admin/technique/vue',
        ] as $url) {
            $this->get($url)->assertSuccessful()->assertSee('amb-module-hero', false)->assertSee('Accès rapides');
        }

        $this->get('/admin/scolarite')->assertSuccessful()->assertSee('amb-module-hero', false);
    }

    public function test_module_navigation_groups_other_authorized_modules(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');

        $this->actingAs($user)
            ->get('/admin/rh')
            ->assertSuccessful()
            ->assertSee('/admin/employes', false)
            ->assertSee('AUTRES MODULES')->assertSee('/admin/finances', false)
            ->assertSee('/admin/socle', false);

        $this->get('/admin/finances')
            ->assertSuccessful()
            ->assertDontSee('/admin/finances/paiements', false)
            ->assertSee('/admin/rh', false)
            ->assertSee('/admin/socle', false);

        $this->get('/admin/eleves')
            ->assertSuccessful()
            ->assertSee('/admin/eleves', false)
            ->assertSee('/admin/rh', false)
            ->assertSee('/admin/socle', false);
    }

    public function test_scolarite_and_pedagogie_dashboards_link_to_their_resources(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');

        $this->actingAs($user)
            ->get('/admin/eleves')
            ->assertSuccessful()
            ->assertSee('/admin/eleves', false)
            ->assertSee('/admin/inscriptions', false)
            ->assertDontSee('/admin/matieres', false);

        $this->get('/admin/pedagogie')
            ->assertSuccessful()
            ->assertSee('/admin/matieres', false)
            ->assertSee('/admin/emploi-du-temps', false)
            ->assertDontSee('/admin/eleves', false);
    }

    public function test_parent_has_no_staff_shortcuts(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Parent');
        $this->actingAs($user);
        foreach (['RH', 'Finances', 'Pedagogie', 'Technique'] as $module) {
            $this->assertSame([], ModuleExperience::shortcuts($module));
        }
        $this->assertFalse(ModuleExperience::canOpenUrl(url('/admin/users')));
    }
}
