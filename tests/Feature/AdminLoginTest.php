<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_login_via_livewire_component(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@ambassadors.com',
            'password' => bcrypt('password'),
            'statut' => 'actif',
        ]);
        $admin->assignRole('Fondateur');

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@ambassadors.com',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/accueil');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@ambassadors.com',
            'password' => bcrypt('password'),
            'statut' => 'actif',
        ]);
        $admin->assignRole('Fondateur');

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@ambassadors.com',
                'password' => 'wrong-password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
    }
}
