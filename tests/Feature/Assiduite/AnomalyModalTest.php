<?php

namespace Tests\Feature\Assiduite;

use App\Models\User;
use App\Modules\Assiduite\Filament\Resources\AnomalieAssiduiteResource\Pages\ListAnomalies;
use App\Modules\Assiduite\Models\AnomalieAssiduite;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnomalyModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolution_from_modal_keeps_actor_and_date(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);
        $record = AnomalieAssiduite::create(['type' => 'test', 'date_anomalie' => today(), 'source' => 'manuel', 'description' => 'Test', 'priorite' => 'normale', 'statut' => 'detectee']);
        Livewire::test(ListAnomalies::class)->mountAction([TestAction::make('view')->table($record), 'edit'])->fillForm(['statut' => 'corrigee', 'resolution' => 'Vérifié'])->callMountedAction()->assertHasNoActionErrors()->assertNoRedirect();
        $this->assertSame($user->id, $record->fresh()->resolue_par);
        $this->assertNotNull($record->fresh()->resolue_le);
    }
}
