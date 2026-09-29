<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Filament\Resources\GrilleSalarialeResource;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceSalaryGridPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_salary_grid_is_presented_in_finances(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');

        $this->actingAs($user)
            ->get(GrilleSalarialeResource::getUrl())
            ->assertSuccessful()
            ->assertSee('Grille salariale')
            ->assertSee('Junior')
            ->assertSee('Confirmé')
            ->assertSee('Déduit des contrats FACILG 2020–2026');

        $this->assertDatabaseHas('grilles_salariales', ['salaire_base' => 36800, 'taux_horaire' => 1200]);
        $this->assertDatabaseHas('grilles_salariales', ['salaire_base' => 50000, 'taux_horaire' => 1500]);
    }
}
