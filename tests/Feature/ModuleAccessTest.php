<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Support\ModuleAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Fondateur', 'Directeur', 'Comptable', 'SurveillantGeneral', 'ChargeLogistique', 'Enseignant', 'Parent', 'Eleve'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_staff_only_sees_modules_allowed_for_their_role(): void
    {
        $comptable = $this->userWithRole('Comptable');

        $this->assertFalse(ModuleAccess::canAccessModule($comptable, 'Finances'));
        $this->assertFalse(ModuleAccess::canAccessModule($comptable, 'Scolarite'));
        $this->assertFalse(ModuleAccess::canAccessModule($comptable, 'RH'));
        $this->assertFalse(ModuleAccess::canAccessModule($comptable, 'Socle'));
        $this->assertTrue(ModuleAccess::canSeeModuleEntry($comptable, 'Scolarite'));
    }

    public function test_every_active_staff_member_can_access_registration_only_without_full_scolarite_access(): void
    {
        $logistique = $this->userWithRole('ChargeLogistique');

        $this->assertFalse(ModuleAccess::canAccessModule($logistique, 'Scolarite'));
        $this->assertTrue(ModuleAccess::canSeeModuleEntry($logistique, 'Scolarite'));
        $this->assertTrue(ModuleAccess::canAccessComponent(
            $logistique,
            'App\\Modules\\Scolarite\\Filament\\Resources\\InscriptionResource\\Pages\\CreateInscription',
        ));
        $this->assertFalse(ModuleAccess::canAccessComponent(
            $logistique,
            'App\\Modules\\Scolarite\\Filament\\Resources\\EleveResource\\Pages\\ListEleves',
        ));
    }

    public function test_parent_and_student_cannot_enter_staff_modules(): void
    {
        foreach (['Parent', 'Eleve'] as $role) {
            $user = $this->userWithRole($role);

            foreach (['Socle', 'RH', 'Scolarite', 'Pedagogie', 'Finances', 'Assiduite', 'Communication'] as $module) {
                $this->assertFalse(ModuleAccess::canSeeModuleEntry($user, $module));
            }
        }
    }

    public function test_suspended_staff_cannot_enter_any_module(): void
    {
        $enseignant = $this->userWithRole('Enseignant', 'suspendu');

        $this->assertFalse(ModuleAccess::canAccessModule($enseignant, 'Pedagogie'));
        $this->assertFalse(ModuleAccess::canStartRegistration($enseignant));
    }

    public function test_non_founder_staff_is_sent_from_admin_to_module_home(): void
    {
        $enseignant = $this->userWithRole('Enseignant');

        $this->actingAs($enseignant)
            ->get('/admin')
            ->assertRedirect('/accueil');
    }

    public function test_founder_uses_the_authenticated_module_home(): void
    {
        $fondateur = $this->userWithRole('Fondateur');

        $this->actingAs($fondateur)
            ->get('/accueil')
            ->assertSuccessful()
            ->assertSee('Bienvenue dans votre espace')
            ->assertSee('Administration');
    }

    public function test_comptable_sees_registration_entry_but_not_full_finance_module(): void
    {
        $comptable = $this->userWithRole('Comptable');

        $this->assertTrue(ModuleAccess::canAccessComponent(
            $comptable,
            'App\\Modules\\Finances\\Filament\\Resources\\FacturePreinscriptionResource\\Pages\\ListFacturesPreinscription',
        ));
        $this->assertFalse(ModuleAccess::canAccessComponent(
            $comptable,
            'App\\Modules\\Finances\\Filament\\Resources\\BudgetResource\\Pages\\ListBudgets',
        ));

        $this->actingAs($comptable)
            ->get('/accueil')
            ->assertSuccessful()
            ->assertSee('Scolarité')
            ->assertDontSee('Finances');
    }

    private function userWithRole(string $role, string $statut = 'actif'): User
    {
        $user = User::factory()->create(['statut' => $statut]);
        $user->assignRole($role);

        return $user;
    }
}
