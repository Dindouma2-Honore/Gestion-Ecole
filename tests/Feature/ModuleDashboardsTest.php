<?php

declare(strict_types=1);

namespace Tests\Feature;

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
            ->assertSee('/admin/eleves', false)
            ->assertDontSee('href="http://localhost/admin/scolarite"', false)
            ->assertSee('Pédagogie')
            ->assertSee('Finances')
            ->assertSee('Assiduité');

        $this->get('/admin')->assertRedirect('/accueil');
    }

    public function test_every_module_has_an_independent_dashboard_route(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);

        $this->get('/admin/socle')->assertRedirect('/admin/users');

        foreach ([
            '/admin/rh',
            '/admin/pedagogie',
            '/admin/finances',
            '/admin/assiduite',
        ] as $url) {
            $this->get($url)->assertSuccessful();
        }

        $this->get('/admin/scolarite')->assertRedirect('/admin/eleves');
    }

    public function test_module_navigation_does_not_expose_other_modules(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');

        $this->actingAs($user)
            ->get('/admin/rh')
            ->assertSuccessful()
            ->assertSee('/admin/employes', false)
            ->assertDontSee('/admin/finances', false)
            ->assertDontSee('/admin/socle', false);

        $this->get('/admin/finances')
            ->assertSuccessful()
            ->assertSee('/admin/finances/paiements', false)
            ->assertDontSee('/admin/rh', false)
            ->assertDontSee('/admin/socle', false);

        $this->get('/admin/eleves')
            ->assertSuccessful()
            ->assertSee('/admin/eleves', false)
            ->assertDontSee('/admin/rh', false)
            ->assertDontSee('/admin/socle', false);
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
}
