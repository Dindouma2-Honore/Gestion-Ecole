<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Filament\Resources\CategoriePersonnelResource;
use App\Modules\RH\Filament\Resources\CategoriePersonnelResource\Pages\CreateCategoriePersonnel;
use App\Modules\RH\Filament\Resources\PersonnelRoleResource;
use App\Modules\RH\Filament\Resources\PersonnelRoleResource\Pages\CreatePersonnelRole;
use App\Modules\RH\Filament\Resources\PosteAdministratifResource;
use App\Modules\RH\Filament\Resources\PosteAdministratifResource\Pages\CreatePosteAdministratif;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RHRolesFonctionsCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_and_functions_are_listed_and_can_be_created_from_personnel(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);

        $this->get('/admin/employes')
            ->assertSuccessful()
            ->assertSee('Rôles')
            ->assertSee('Fonctions')
            ->assertSee('Catégories');

        $this->get(PersonnelRoleResource::getUrl())
            ->assertSuccessful()
            ->assertSee('Fondateur')
            ->assertSee('Enseignant')
            ->assertSee('Nouveau rôle');

        Livewire::test(CreatePersonnelRole::class)
            ->fillForm(['name' => 'Bibliothécaire', 'guard_name' => 'web'])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertDatabaseHas('roles', ['name' => 'Bibliothécaire', 'guard_name' => 'web']);

        Livewire::test(CreatePosteAdministratif::class)
            ->fillForm(['nom' => 'Responsable de bibliothèque', 'description' => 'Gestion documentaire', 'actif' => true])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertDatabaseHas('postes_administratifs', ['nom' => 'Responsable de bibliothèque', 'actif' => true]);

        $this->get(PosteAdministratifResource::getUrl())
            ->assertSuccessful()
            ->assertSee('Responsable de bibliothèque')
            ->assertSee('Nouvelle fonction');

        Livewire::test(CreateCategoriePersonnel::class)
            ->fillForm(['nom' => 'Personnel administratif', 'description' => 'Personnel non enseignant', 'actif' => true])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertDatabaseHas('categories_personnel', ['nom' => 'Personnel administratif', 'actif' => true]);

        $this->get(CategoriePersonnelResource::getUrl())
            ->assertSuccessful()
            ->assertSee('Personnel administratif')
            ->assertSee('Nouvelle catégorie');
    }
}
