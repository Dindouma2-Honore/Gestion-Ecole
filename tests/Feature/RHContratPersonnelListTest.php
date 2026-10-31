<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Filament\Resources\ContratResource\Pages\CreateContrat;
use App\Modules\RH\Filament\Resources\ContratResource\Pages\ListContrats;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RHContratPersonnelListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        \App\Modules\Socle\Models\AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'active',
        ]);
    }

    public function test_create_contract_form_loads_all_active_personnel(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif', 'must_change_password' => false]);
        $fondateur->assignRole('Fondateur');

        $emp1 = Employe::create([
            'matricule' => 'EMP-001',
            'nom' => 'KAMGA',
            'prenom' => 'PAUL',
            'poste' => 'ENSEIGNANT',
            'date_embauche' => '2026-09-01',
            'statut' => 'actif',
        ]);

        $emp2 = Employe::create([
            'matricule' => 'EMP-002',
            'nom' => 'NDAH',
            'prenom' => 'JOHN',
            'poste' => 'PROPRIETOR',
            'date_embauche' => '2026-09-01',
            'statut' => 'actif',
        ]);

        // Même si un employé a déjà un contrat existant, il doit être dans la liste pour un renouvellement
        Contrat::create([
            'employe_id' => $emp2->id,
            'type' => 'CDI',
            'categorie_paie' => 'fixe',
            'salaire_base' => 150000,
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'actif',
        ]);

        $test = Livewire::actingAs($fondateur)
            ->test(CreateContrat::class);

        $options = $test->instance()->form->getComponents()[0]->getOptions();

        $this->assertArrayHasKey($emp1->id, $options);
        $this->assertArrayHasKey($emp2->id, $options);
        $this->assertStringContainsString('KAMGA PAUL', $options[$emp1->id]);
        $this->assertStringContainsString('NDAH JOHN', $options[$emp2->id]);
    }

    public function test_can_create_new_contract_for_personnel(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif', 'must_change_password' => false]);
        $fondateur->assignRole('Fondateur');

        $emp = Employe::create([
            'matricule' => 'EMP-003',
            'nom' => 'BIH',
            'prenom' => 'CLARA',
            'poste' => 'SECRETAIRE',
            'date_embauche' => '2026-09-01',
            'statut' => 'actif',
        ]);

        Livewire::actingAs($fondateur)
            ->test(CreateContrat::class)
            ->fillForm([
                'employe_id' => $emp->id,
                'type' => 'CDI',
                'categorie_paie' => 'fixe',
                'salaire_base' => 120000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('contrats', [
            'employe_id' => $emp->id,
            'type' => 'CDI',
            'statut' => 'actif',
            'salaire_base' => 120000,
        ]);
    }

    public function test_can_renew_contract_for_personnel_with_active_contract(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif', 'must_change_password' => false]);
        $fondateur->assignRole('Fondateur');

        $emp = Employe::create([
            'matricule' => 'EMP-004',
            'nom' => 'FOKOU',
            'prenom' => 'JEAN',
            'poste' => 'COMPTABLE',
            'date_embauche' => '2026-09-01',
            'statut' => 'actif',
        ]);

        $ancienContrat = Contrat::create([
            'employe_id' => $emp->id,
            'type' => 'CDD',
            'categorie_paie' => 'fixe',
            'salaire_base' => 100000,
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'actif',
        ]);

        Livewire::actingAs($fondateur)
            ->test(CreateContrat::class)
            ->fillForm([
                'employe_id' => $emp->id,
                'type' => 'CDI',
                'categorie_paie' => 'fixe',
                'salaire_base' => 130000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // L'ancien contrat est clôturé et marqué expiré
        $ancienContrat->refresh();
        $this->assertSame('expire', $ancienContrat->statut);

        // Le nouveau contrat est actif avec le nouveau salaire
        $this->assertDatabaseHas('contrats', [
            'employe_id' => $emp->id,
            'type' => 'CDI',
            'statut' => 'actif',
            'salaire_base' => 130000,
        ]);
    }

    public function test_list_contrats_header_action_creates_and_loads_personnel(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif', 'must_change_password' => false]);
        $fondateur->assignRole('Fondateur');

        $emp = Employe::create([
            'matricule' => 'EMP-005',
            'nom' => 'DJOUKOUE',
            'prenom' => 'HORNELLA',
            'poste' => 'ENSEIGNANT',
            'date_embauche' => '2026-09-01',
            'statut' => 'actif',
        ]);

        Livewire::actingAs($fondateur)
            ->test(ListContrats::class)
            ->mountAction('create')
            ->setActionData([
                'employe_id' => $emp->id,
                'type' => 'CDI',
                'categorie_paie' => 'fixe',
                'salaire_base' => 140000,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('contrats', [
            'employe_id' => $emp->id,
            'type' => 'CDI',
            'statut' => 'actif',
            'salaire_base' => 140000,
        ]);
    }
}
