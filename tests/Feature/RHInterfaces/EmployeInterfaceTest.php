<?php

declare(strict_types=1);

namespace Tests\Feature\RHInterfaces;

use App\Models\User;
use App\Modules\RH\Contracts\EmployeServiceContract;
use App\Modules\RH\Filament\Resources\EmployeResource;
use App\Modules\RH\Filament\Resources\EmployeResource\Pages\CreateEmploye;
use App\Modules\RH\Filament\Resources\EmployeResource\Pages\EditEmploye;
use App\Modules\RH\Filament\Resources\EmployeResource\Pages\ListEmployes;
use App\Modules\RH\Filament\Resources\EmployeResource\Pages\ViewEmploye;
use App\Modules\RH\Models\Candidature;
use App\Modules\RH\Models\DiplomePersonnel;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\PosteAdministratif;
use App\Modules\RH\Models\SanctionPersonnel;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create(['statut' => 'actif']);
        $this->adminUser->assignRole('Fondateur');

        Role::firstOrCreate(['name' => 'Comptable', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Directeur', 'guard_name' => 'web']);
    }

    public function test_acces_page_employes_selon_permission(): void
    {
        // Utilisateur non autorisé
        $unauthorized = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($unauthorized);
        $this->get(EmployeResource::getUrl())->assertForbidden();

        // Utilisateur autorisé
        $this->actingAs($this->adminUser);
        $this->get(EmployeResource::getUrl())->assertSuccessful();
    }

    public function test_liste_employes_recherche_et_chargement(): void
    {
        $this->actingAs($this->adminUser);

        $emp1 = Employe::query()->create([
            'matricule' => 'EMP-001',
            'nom' => 'Mbarga',
            'prenom' => 'Joseph',
            'email' => 'joseph.mbarga@ambassadors.test',
            'telephone' => '690112233',
            'poste' => 'Comptable',
            'date_embauche' => '2026-01-15',
            'statut' => 'actif',
        ]);

        $emp2 = Employe::query()->create([
            'matricule' => 'EMP-002',
            'nom' => 'Abena',
            'prenom' => 'Sophie',
            'email' => 'sophie.abena@ambassadors.test',
            'telephone' => '690445566',
            'poste' => 'Directeur',
            'date_embauche' => '2026-02-01',
            'statut' => 'actif',
        ]);

        Livewire::test(ListEmployes::class)
            ->assertCanSeeTableRecords([$emp1, $emp2])
            ->searchTable('EMP-001')
            ->assertCanSeeTableRecords([$emp1])
            ->assertCanNotSeeTableRecords([$emp2]);
    }

    public function test_creation_employe_exige_candidature_retenue(): void
    {
        $this->actingAs($this->adminUser);

        // Sans candidature : échec de validation
        Livewire::test(CreateEmploye::class)
            ->fillForm([
                'nom' => 'SansDossier',
                'prenom' => 'Jean',
                'email' => 'jean.sans@ambassadors.test',
            ])
            ->call('create')
            ->assertHasFormErrors(['candidature_id']);

        // Avec candidature retenue
        $role = Role::where('name', 'Comptable')->firstOrFail();
        $posteAdmin = PosteAdministratif::query()->create([
            'nom' => 'Comptable',
            'actif' => true,
        ]);

        $candidature = Candidature::query()->create([
            'nom' => 'Tchinda',
            'prenom' => 'Paul',
            'email' => 'paul.tchinda@ambassadors.test',
            'telephone' => '677112233',
            'poste_souhaite' => 'Comptable',
            'statut' => 'retenue',
        ]);

        Livewire::test(CreateEmploye::class)
            ->fillForm([
                'candidature_id' => $candidature->id,
                'nom' => 'Tchinda',
                'prenom' => 'Paul',
                'email' => 'paul.tchinda@ambassadors.test',
                'telephone' => '677112233',
                'poste_administratif_id' => $posteAdmin->id,
                'role_id' => $role->id,
                'formule_paie' => 'fixe',
                'type_contrat' => 'CDI',
                'date_debut_contrat' => '2026-10-01',
                'date_embauche' => '2026-10-01',
                'salaire_base' => 200000,
                'statut' => 'actif',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Intégration finalisée');

        $this->assertDatabaseHas('employes', [
            'nom' => 'Tchinda',
            'prenom' => 'Paul',
            'email' => 'paul.tchinda@ambassadors.test',
            'poste_administratif_id' => $posteAdmin->id,
            'statut' => 'actif',
        ]);

        $this->assertSame('embauche', $candidature->fresh()->statut);
    }

    public function test_modification_employe_sans_affecter_statut_user_ou_contrat(): void
    {
        $this->actingAs($this->adminUser);

        $role = Role::where('name', 'Comptable')->firstOrFail();
        $user = User::factory()->create(['statut' => 'actif']);
        $employe = Employe::query()->create([
            'matricule' => 'EMP-MOD-01',
            'nom' => 'Kuate',
            'prenom' => 'Eric',
            'email' => 'eric.kuate@ambassadors.test',
            'telephone' => '677889900',
            'role_id' => $role->id,
            'poste' => 'Comptable',
            'date_embauche' => '2026-01-01',
            'statut' => 'actif',
            'user_id' => $user->id,
        ]);

        $contrat = $employe->contrats()->create([
            'type' => 'CDI',
            'categorie_paie' => 'fixe',
            'salaire_base' => 250000,
            'date_debut' => '2026-01-01',
            'statut' => 'actif',
        ]);
        $employe->update(['contrat_id' => $contrat->id]);

        Livewire::test(EditEmploye::class, ['record' => $employe->getRouteKey()])
            ->fillForm([
                'telephone' => '699112233',
                'departement' => 'Finance et Comptabilité',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $employeFraiche = $employe->fresh();
        $this->assertSame('699112233', $employeFraiche->telephone);
        $this->assertSame('Finance et Comptabilité', $employeFraiche->departement);

        // Statuts indépendants non altérés
        $this->assertSame('actif', $employeFraiche->statut);
        $this->assertSame('actif', $user->fresh()->statut);
        $this->assertSame('actif', $contrat->fresh()->statut);
    }

    public function test_modification_changement_poste_avec_motif_met_a_jour_historique(): void
    {
        $this->actingAs($this->adminUser);

        $role = Role::where('name', 'Comptable')->firstOrFail();
        $poste1 = PosteAdministratif::query()->create(['nom' => 'Comptable Junior', 'actif' => true]);
        $poste2 = PosteAdministratif::query()->create(['nom' => 'Chef Comptable', 'actif' => true]);

        $employe = Employe::query()->create([
            'matricule' => 'EMP-CAR-01',
            'nom' => 'Njoya',
            'prenom' => 'Samuel',
            'email' => 'samuel.njoya@ambassadors.test',
            'role_id' => $role->id,
            'poste_administratif_id' => $poste1->id,
            'poste' => 'Comptable Junior',
            'date_embauche' => '2026-01-01',
            'statut' => 'actif',
        ]);

        Livewire::test(EditEmploye::class, ['record' => $employe->getRouteKey()])
            ->fillForm([
                'poste_administratif_id' => $poste2->id,
                'motif_changement_poste' => 'Promotion interne au mérite',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($poste2->id, $employe->fresh()->poste_administratif_id);
        $this->assertSame('Chef Comptable', $employe->fresh()->poste);

        $this->assertDatabaseHas('personnel_postes_historique', [
            'employe_id' => $employe->id,
            'poste_administratif_id' => $poste2->id,
            'motif' => 'Promotion interne au mérite',
        ]);
    }

    public function test_dossier_employe_affiche_toutes_les_sections_et_relations(): void
    {
        $this->actingAs($this->adminUser);

        $posteAdmin = PosteAdministratif::query()->create(['nom' => 'Comptable Général', 'actif' => true]);
        $candidature = Candidature::query()->create([
            'nom' => 'Mballa',
            'prenom' => 'Christian',
            'email' => 'christian.mballa@ambassadors.test',
            'poste_souhaite' => 'Comptable Général',
            'cv_path' => 'personnel/candidatures/cv_mballa.pdf',
            'statut' => 'embauche',
        ]);

        $employe = Employe::query()->create([
            'matricule' => 'EMP-DOSSIER-01',
            'nom' => 'Mballa',
            'prenom' => 'Christian',
            'email' => 'christian.mballa@ambassadors.test',
            'telephone' => '655443322',
            'poste_administratif_id' => $posteAdmin->id,
            'poste' => 'Comptable Général',
            'date_embauche' => '2025-09-01',
            'statut' => 'actif',
            'candidature_id' => $candidature->id,
        ]);
        $candidature->update(['employe_id' => $employe->id]);

        $contrat = $employe->contrats()->create([
            'type' => 'CDI',
            'categorie_paie' => 'fixe',
            'salaire_base' => 300000,
            'date_debut' => '2025-09-01',
            'statut' => 'actif',
        ]);
        $employe->update(['contrat_id' => $contrat->id]);

        DiplomePersonnel::query()->create([
            'personnel_id' => $employe->id,
            'nom' => 'Master Comptabilité Finance',
            'type' => 'academique',
            'etablissement' => 'Université de Yaoundé II',
            'annee_obtention' => 2022,
        ]);

        SanctionPersonnel::query()->create([
            'employe_id' => $employe->id,
            'type' => 'avertissement',
            'date_sanction' => '2026-03-01',
            'motif' => 'Retard répété aux réunions de direction',
            'statut' => 'validee',
            'created_by' => $this->adminUser->id,
        ]);

        // 1. Consultation via la page ViewEmploye
        Livewire::test(ViewEmploye::class, ['record' => $employe->getRouteKey()])
            ->assertSee('Mballa Christian')
            ->assertSee('EMP-DOSSIER-01')
            ->assertSee('Comptable Général')
            ->assertSee('300 000 FCFA')
            ->assertSee('Master Comptabilité Finance')
            ->assertSee('Retard répété aux réunions de direction')
            ->assertSee('cv_mballa.pdf');

        // 2. Consultation et action sur la liste
        Livewire::test(ListEmployes::class)
            ->assertCanSeeTableRecords([$employe])
            ->assertTableActionVisible('view', $employe);
    }

    public function test_employe_sans_compte_user_fonctionne_normalement(): void
    {
        $this->actingAs($this->adminUser);

        $role = Role::where('name', 'Comptable')->firstOrFail();
        $employe = Employe::query()->create([
            'matricule' => 'EMP-NO-USER',
            'nom' => 'Fomekong',
            'prenom' => 'David',
            'email' => 'david.fomekong@ambassadors.test',
            'telephone' => '688990011',
            'role_id' => $role->id,
            'poste' => 'Agent Logistique',
            'date_embauche' => '2026-01-01',
            'statut' => 'actif',
            'user_id' => null,
        ]);

        Livewire::test(EditEmploye::class, ['record' => $employe->getRouteKey()])
            ->fillForm([
                'telephone' => '677001122',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('677001122', $employe->fresh()->telephone);
        $this->assertNull($employe->fresh()->user_id);
    }

    public function test_activation_compte_user_depuis_interface(): void
    {
        $this->actingAs($this->adminUser);

        $role = Role::where('name', 'Comptable')->firstOrFail();
        $employe = Employe::query()->create([
            'matricule' => 'EMP-USER-ADD',
            'nom' => 'Simbe',
            'prenom' => 'Gervais',
            'email' => 'gervais.simbe@ambassadors.test',
            'telephone' => '655998877',
            'role_id' => $role->id,
            'poste' => 'Comptable',
            'date_embauche' => '2026-02-01',
            'statut' => 'actif',
            'user_id' => null,
        ]);

        Livewire::test(ListEmployes::class)
            ->assertTableActionVisible('activer_acces', $employe)
            ->callTableAction('activer_acces', $employe, data: [
                'email' => 'gervais.simbe@ambassadors.test',
                'role_id' => $role->id,
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Accès opérationnel');

        $employeFraiche = $employe->fresh();
        $this->assertNotNull($employeFraiche->user_id);

        $user = User::query()->find($employeFraiche->user_id);
        $this->assertNotNull($user);
        $this->assertSame('gervais.simbe@ambassadors.test', $user->email);
        $this->assertTrue($user->hasRole('Comptable'));
        $this->assertTrue($user->must_change_password);
    }

    public function test_erreurs_ui_gerees_sans_exception_brute(): void
    {
        $this->actingAs($this->adminUser);

        $role = Role::where('name', 'Comptable')->firstOrFail();
        $poste1 = PosteAdministratif::query()->create(['nom' => 'Poste Initial', 'actif' => true]);
        $poste2 = PosteAdministratif::query()->create(['nom' => 'Poste Suivant', 'actif' => true]);

        $employe = Employe::query()->create([
            'matricule' => 'EMP-ERR-01',
            'nom' => 'Meva',
            'prenom' => 'Patrick',
            'email' => 'patrick.meva@ambassadors.test',
            'role_id' => $role->id,
            'poste' => 'Poste Initial',
            'poste_administratif_id' => $poste1->id,
            'date_embauche' => '2026-01-01',
            'statut' => 'actif',
        ]);

        // 1. Changement de fonction administrative sans motif -> bloqué par validation formulaire
        Livewire::test(EditEmploye::class, ['record' => $employe->getRouteKey()])
            ->fillForm([
                'poste_administratif_id' => $poste2->id,
                'motif_changement_poste' => '',
            ])
            ->call('save')
            ->assertHasFormErrors(['motif_changement_poste']);

        // 2. Erreur métier capturée gracieusement dans l'action activer_acces
        $this->mock(EmployeServiceContract::class, function ($mock): void {
            $mock->shouldReceive('creerAcces')
                ->andThrow(new \DomainException('Impossible de créer un compte avec cet identifiant'));
        });

        Livewire::test(ListEmployes::class)
            ->callTableAction('activer_acces', $employe, data: [
                'email' => 'patrick.meva@ambassadors.test',
                'role_id' => Role::first()->id,
            ])
            ->assertNotified('Erreur lors de l’attribution de l’accès');
    }
}
