<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Filament\Resources\EmployeResource;
use App\Modules\RH\Filament\Resources\EmployeResource\Pages\CreateEmploye;
use App\Modules\RH\Filament\Resources\EmployeResource\Pages\EditEmploye;
use App\Modules\RH\Filament\Resources\EmployeResource\Pages\ListEmployes;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\PosteAdministratif;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RHPersonnelCardsCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_personnel_cards_and_crud_pages_are_available(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $roleId = (int) Role::query()->where('name', 'Enseignant')->value('id');
        $poste = PosteAdministratif::query()->create([
            'nom' => 'Secrétariat général',
            'actif' => true,
        ]);
        $employe = Employe::query()->create([
            'matricule' => 'EMP-CARD-001',
            'nom' => 'Mballa',
            'prenom' => 'Aline',
            'role_id' => $roleId,
            'poste' => 'Secrétaire',
            'poste_administratif_id' => $poste->id,
            'departement' => 'Administration',
            'date_embauche' => '2026-09-01',
            'statut' => 'actif',
        ]);

        $this->actingAs($user)
            ->get(EmployeResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('Aline Mballa')
            ->assertSee('EMP-CARD-001')
            ->assertSee('Nouveau membre du personnel');

        $this->get(EmployeResource::getUrl('view', ['record' => $employe]))
            ->assertSuccessful()
            ->assertSee('Identité du membre du personnel')
            ->assertSee('Secrétariat général')
            ->assertSee('Modifier')
            ->assertSee('Supprimer');

        $this->get(EmployeResource::getUrl('edit', ['record' => $employe]))->assertSuccessful();
        $this->get(EmployeResource::getUrl('create'))->assertSuccessful();

        Livewire::test(ListEmployes::class)
            ->assertCanSeeTableRecords([$employe])
            ->searchTable('EMP-CARD-001')
            ->assertCanSeeTableRecords([$employe])
            ->assertTableActionVisible('view', $employe)
            ->assertTableActionVisible('edit', $employe)
            ->assertTableActionVisible('delete', $employe);

        Livewire::test(CreateEmploye::class)
            ->fillForm([
                'nom' => 'Essomba', 'prenom' => 'Marc', 'email' => 'marc.essomba@ambassadors.test',
                'role_id' => $roleId, 'date_embauche' => '2026-09-02',
                'responsabilite_fixe' => true, 'responsabilite_horaire' => false,
                'type_contrat' => 'CDI', 'date_debut_contrat' => '2026-09-02', 'salaire_base' => 180000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $nouvelEmploye = Employe::query()->where('email', 'marc.essomba@ambassadors.test')->firstOrFail();
        $this->assertMatchesRegularExpression('/^EMP-\d{4}-\d{4}$/', $nouvelEmploye->matricule);
        $this->assertNotNull($nouvelEmploye->user_id);
        $this->assertSame('Enseignant', $nouvelEmploye->poste);
        $this->assertSame('actif', $nouvelEmploye->statut);
        $this->assertDatabaseHas('contrats', [
            'employe_id' => $nouvelEmploye->id,
            'categorie_paie' => 'fixe',
            'salaire_base' => 180000,
        ]);
        $this->assertDatabaseHas('employe_historique_carriere', [
            'employe_id' => $nouvelEmploye->id,
            'evenement' => 'embauche',
        ]);

        Livewire::test(EditEmploye::class, ['record' => $employe->getRouteKey()])
            ->fillForm(['nom' => 'Mballa Modifié'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertDatabaseHas('employes', ['id' => $employe->id, 'nom' => 'Mballa Modifié']);

        Livewire::test(ListEmployes::class)
            ->callTableAction('delete', $employe->fresh());
        $this->assertDatabaseMissing('employes', ['id' => $employe->id]);
    }
}
