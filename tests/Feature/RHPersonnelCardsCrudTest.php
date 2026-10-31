<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Filament\Pages\RHDashboard;
use App\Modules\RH\Filament\Resources\EmployeResource;
use App\Modules\RH\Filament\Resources\EmployeResource\Pages\CreateEmploye;
use App\Modules\RH\Filament\Resources\EmployeResource\Pages\EditEmploye;
use App\Modules\RH\Filament\Resources\EmployeResource\Pages\ListEmployes;
use App\Modules\RH\Filament\Widgets\RHOverview;
use App\Modules\RH\Models\Candidature;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\PosteAdministratif;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            ->assertSee('Mballa Aline')
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
            ->assertTableActionVisible('delete', $employe)
            ->assertTableColumnExists('nom_complet')
            ->assertTableColumnExists('contratPrincipal.categorie_paie')
            ->assertTableColumnDoesNotExist('departement')
            ->call('toggleLayout')
            ->assertTableColumnExists('carte_personnel')
            ->mountTableAction('view', $employe)
            ->assertMountedActionModalSee('Informations complètes')
            ->assertMountedActionModalSee('Secrétariat général');

        $candidature = Candidature::query()->create([
            'nom' => 'Essomba', 'prenom' => 'Marc', 'email' => 'marc.essomba@ambassadors.test',
            'poste_souhaite' => 'Enseignant', 'statut' => 'retenue',
        ]);

        Livewire::test(CreateEmploye::class)
            ->fillForm([
                'candidature_id' => $candidature->id,
                'nom' => 'Essomba', 'prenom' => 'Marc', 'email' => 'marc.essomba@ambassadors.test',
                'role_id' => $roleId, 'date_embauche' => '2026-09-02',
                'formule_paie' => 'fixe',
                'type_contrat' => 'CDI', 'date_debut_contrat' => '2026-09-02', 'salaire_base' => 180000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $nouvelEmploye = Employe::query()->where('email', 'marc.essomba@ambassadors.test')->firstOrFail();
        $this->assertMatchesRegularExpression('/^EMP-\d{4}-\d{4}$/', $nouvelEmploye->matricule);
        $this->assertNotNull($nouvelEmploye->user_id);
        $this->assertSame('embauche', $candidature->fresh()->statut);
        $this->assertSame($nouvelEmploye->id, $candidature->fresh()->employe_id);
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

    public function test_poste_administratif_est_la_source_de_verite_du_poste_legacy(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $roleId = (int) Role::query()->where('name', 'Enseignant')->value('id');
        $posteA = PosteAdministratif::query()->create(['nom' => 'Direction pédagogique', 'actif' => true]);
        $posteB = PosteAdministratif::query()->create(['nom' => 'Secrétariat central', 'actif' => true]);
        $candidature = Candidature::query()->create([
            'nom' => 'Ngo', 'prenom' => 'Bénédicte', 'email' => 'benedicte.ngo@ambassadors.test',
            'poste_souhaite' => 'Enseignant', 'statut' => 'retenue',
        ]);

        Livewire::test(CreateEmploye::class)
            ->fillForm([
                'candidature_id' => $candidature->id,
                'nom' => 'Ngo',
                'prenom' => 'Bénédicte',
                'email' => 'benedicte.ngo@ambassadors.test',
                'role_id' => $roleId,
                'poste_administratif_id' => $posteA->id,
                'date_embauche' => '2026-09-05',
                'formule_paie' => 'fixe',
                'type_contrat' => 'CDI', 'date_debut_contrat' => '2026-09-05', 'salaire_base' => 150000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $employe = Employe::query()->where('email', 'benedicte.ngo@ambassadors.test')->firstOrFail();
        $this->assertSame($posteA->id, (int) $employe->poste_administratif_id);
        $this->assertSame('Direction pédagogique', $employe->poste);
        $this->assertDatabaseHas('personnel_postes_historique', [
            'employe_id' => $employe->id,
            'poste_administratif_id' => $posteA->id,
            'motif' => 'Affectation initiale',
            'date_fin' => null,
        ]);

        $this->actingAs($fondateur);
        Livewire::test(EditEmploye::class, ['record' => $employe->getRouteKey()])
            ->fillForm([
                'poste_administratif_id' => $posteB->id,
                'motif_changement_poste' => 'Transfert interne',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $employe->refresh();
        $this->assertSame($posteB->id, (int) $employe->poste_administratif_id);
        $this->assertSame('Secrétariat central', $employe->poste);
        $this->assertSame($employe->posteAdministratif->nom, $employe->poste);

        $ancienne = DB::table('personnel_postes_historique')
            ->where('employe_id', $employe->id)
            ->where('poste_administratif_id', $posteA->id)
            ->first();
        $this->assertNotNull($ancienne);
        $this->assertNotNull($ancienne->date_fin);

        $nouvelle = DB::table('personnel_postes_historique')
            ->where('employe_id', $employe->id)
            ->where('poste_administratif_id', $posteB->id)
            ->whereNull('date_fin')
            ->first();
        $this->assertNotNull($nouvelle);
        $this->assertSame('Transfert interne', $nouvelle->motif);
    }

    public function test_changement_de_role_ne_vide_pas_email_du_compte_utilisateur(): void
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

        Livewire::test(EditEmploye::class, ['record' => $employe->getRouteKey()])
            ->fillForm(['role_id' => $nouveauRole->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('personnel.existant@ambassadors.test', $user->fresh()->email);
        $this->assertTrue($user->fresh()->hasRole('Comptable'));
        $this->assertSame($nouveauRole->id, $employe->fresh()->role_id);
    }

    public function test_tableau_de_bord_rh_compte_les_enseignants_depuis_le_role_du_personnel(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');

        $enseignant = Role::query()->where('name', 'Enseignant')->firstOrFail();
        $comptable = Role::query()->where('name', 'Comptable')->firstOrFail();

        Employe::query()->create([
            'matricule' => 'EMP-TEACH-001',
            'nom' => 'Atangana',
            'prenom' => 'Claire',
            'role_id' => $enseignant->id,
            'poste' => $enseignant->name,
            'date_embauche' => '2026-09-01',
            'statut' => 'actif',
        ]);
        Employe::query()->create([
            'matricule' => 'EMP-COMP-001',
            'nom' => 'Nkoa',
            'prenom' => 'Marc',
            'role_id' => $comptable->id,
            'poste' => $comptable->name,
            'date_embauche' => '2026-09-01',
            'statut' => 'actif',
        ]);

        $widget = new class extends RHOverview
        {
            public function statsForTest(): array
            {
                return $this->getStats();
            }
        };

        $stats = collect($widget->statsForTest());
        $this->assertSame(1, $stats->first(fn ($stat) => $stat->getLabel() === 'Enseignants')->getValue());

        $this->actingAs($fondateur)
            ->get(RHDashboard::getUrl())
            ->assertSuccessful()
            ->assertSee('Tableau de bord');
    }
}
