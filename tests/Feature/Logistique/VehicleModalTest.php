<?php

namespace Tests\Feature\Logistique;

use App\Models\User;
use App\Modules\Logistique\Filament\Resources\VehiculeResource\Pages\ListVehicules;
use App\Modules\Logistique\Models\Vehicule;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_dialog_preserves_edit_and_confirmed_delete(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);
        $vehicle = Vehicule::create(['immatriculation' => 'LT-TEST-01', 'capacite' => 20, 'etat' => 'bon']);
        Livewire::test(ListVehicules::class)
            ->mountAction([TestAction::make('view')->table($vehicle), 'edit'])
            ->fillForm(['capacite' => 25])->callMountedAction()->assertHasNoActionErrors()->assertNoRedirect();
        $this->assertSame(25, (int) $vehicle->fresh()->capacite);
        $component = Livewire::test(ListVehicules::class)
            ->mountAction([TestAction::make('view')->table($vehicle), 'delete']);
        $this->assertModelExists($vehicle);
        $component->callMountedAction()->assertHasNoActionErrors()->assertNoRedirect();
        $this->assertModelMissing($vehicle);
    }
}
