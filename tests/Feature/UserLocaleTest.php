<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_each_user_sees_navigation_in_their_own_language(): void
    {
        $english = User::factory()->create(['statut' => 'actif', 'locale' => 'en']);
        $english->assignRole('Fondateur');
        $this->actingAs($english)->get('/admin/users')->assertSuccessful()
            ->assertSee('Users &amp; permissions', false)
            ->assertSee('Institution structure');

        $french = User::factory()->create(['statut' => 'actif', 'locale' => 'fr']);
        $french->assignRole('Fondateur');
        $this->actingAs($french)->get('/admin/users')->assertSuccessful()
            ->assertSee('Utilisateurs &amp; habilitations', false)
            ->assertSee('Structure de l’établissement');
    }

    public function test_module_portal_is_translated_for_an_english_user(): void
    {
        $user = User::factory()->create(['statut' => 'actif', 'locale' => 'en']);
        $user->assignRole('Fondateur');

        $this->actingAs($user)->get('/accueil')->assertSuccessful()
            ->assertSee('Human resources')
            ->assertSee('Students, enrolments, classes and school records.');
    }
}
