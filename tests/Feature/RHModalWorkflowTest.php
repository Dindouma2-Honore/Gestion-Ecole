<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Filament\Resources\CandidatureResource\Pages\ListCandidatures;
use App\Modules\RH\Filament\Resources\EmployeResource\Pages\ListEmployes;
use App\Modules\RH\Filament\Resources\PersonnelRoleResource\Pages\ListPersonnelRoles;
use App\Modules\RH\Models\Candidature;
use App\Modules\RH\Models\Employe;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RHModalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_modale_personnel_synchronise_le_compte(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');

        $user = User::factory()->create([
            'email' => 'personnel.existant@ambassadors.test',
            'statut' => 'actif',
        ]);
        $ancienRole = Role::query()->where('name', 'Enseignant')->firstOrFail();
        $nouveauRole = Role::query()->where('name', 'Comptable')->firstOrFail();
        $user->assignRole($ancienRole);

        $employe = Employe::query()->create([
            'matricule' => 'EMP-EMAIL-001',
            'nom' => 'Mbarga',
            'prenom' => 'Paul',
            'email' => null,
            'user_id' => $user->id,
            'role_id' => $ancienRole->id,
            'poste' => $ancienRole->name,
            'date_embauche' => '2026-09-01',
            'statut' => 'actif',
        ]);

        $this->actingAs($fondateur);

        Livewire::test(ListEmployes::class)->mountAction([TestAction::make('view')->table($employe), 'edit'])
            ->fillForm(['role_id' => $nouveauRole->id])
            ->callMountedAction()->assertNoRedirect()
            ->assertHasNoFormErrors();

        $this->assertSame('personnel.existant@ambassadors.test', $user->fresh()->email);
        $this->assertTrue($user->fresh()->hasRole('Comptable'));
        $this->assertSame($nouveauRole->id, $employe->fresh()->role_id);
    }

    public function test_suppression_depuis_consultation_demande_confirmation(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);
        $record = Candidature::create([
            'nom' => 'Test', 'prenom' => 'Modal', 'poste_souhaite' => 'Enseignant', 'statut' => 'nouvelle',
        ]);
        $component = Livewire::test(ListCandidatures::class)
            ->mountAction([TestAction::make('view')->table($record), 'delete']);
        $this->assertModelExists($record);
        $component->callMountedAction()->assertNoRedirect()->assertHasNoActionErrors();
        $this->assertModelMissing($record);
    }

    public function test_role_attribue_ne_peut_pas_etre_supprime_depuis_modale(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);
        $role = Role::where('name', 'Fondateur')->firstOrFail();
        Livewire::test(ListPersonnelRoles::class)
            ->assertActionDisabled([TestAction::make('edit')->table($role), 'delete']);
        $this->assertModelExists($role);
    }
}
