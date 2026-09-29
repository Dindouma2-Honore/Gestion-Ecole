<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Support\ModuleAccess;
use App\Models\User;
use App\Modules\Communication\Contracts\NotificationServiceContract;
use App\Modules\RH\Filament\Resources\EmployeResource;
use App\Modules\Socle\Contracts\GroupeServiceContract;
use App\Modules\Socle\Contracts\HabilitationServiceContract;
use App\Modules\Socle\Exceptions\MotifRetraitPermissionGroupeRequisException;
use App\Modules\Socle\Filament\Pages\ManageGroupePermissions;
use App\Modules\Socle\Filament\Pages\ManageHabilitations;
use App\Modules\Socle\Filament\Resources\PermissionResource;
use App\Modules\Socle\Filament\Resources\RoleResource;
use App\Modules\Socle\Filament\Resources\GroupeResource\Pages\ListGroupes;
use App\Modules\Socle\Models\ActivityLog;
use App\Modules\Socle\Models\Fonctionnalite;
use App\Modules\Socle\Models\Groupe;
use App\Modules\Socle\Models\HabilitationGroupeFonctionnalite;
use App\Modules\Socle\Models\HabilitationRoleFonctionnalite;
use App\Modules\Socle\Services\AssignationRoleService;
use App\Modules\Socle\Services\HabilitationService;
use Database\Seeders\HabilitationsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class SocleA9GroupesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(HabilitationsSeeder::class);
    }

    public function test_surveillant_general_reste_un_role_transversal(): void
    {
        $this->assertContains('SurveillantGeneral', HabilitationService::ROLES_STAFF);

        $user = User::factory()->create(['statut' => 'actif']);
        app(AssignationRoleService::class)->assignerRole($user, 'SurveillantGeneral');
        $user->refresh();

        $this->assertNull($user->niveau_id);
        $this->assertTrue(ModuleAccess::canStartRegistration($user));
    }

    public function test_gestion_des_roles_est_reservee_au_fondateur(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');

        $directeur = User::factory()->create(['statut' => 'actif']);
        $directeur->assignRole('Directeur');
        $directeur->givePermissionTo('gerer_utilisateurs');

        $this->actingAs($fondateur);
        $this->assertTrue(RoleResource::canViewAny());
        $this->assertTrue(RoleResource::canCreate());
        $this->assertTrue(PermissionResource::canViewAny());

        $this->actingAs($directeur);
        $this->assertFalse(RoleResource::canViewAny());
        $this->assertFalse(RoleResource::canCreate());
        $this->assertFalse(PermissionResource::canViewAny());
    }

    public function test_creation_dun_groupe_et_ajout_de_membres_sont_audites(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $enseignant = User::factory()->create(['statut' => 'actif']);
        $enseignant->assignRole('Enseignant');
        $this->actingAs($fondateur);

        $groupeService = app(GroupeServiceContract::class);
        $groupe = $groupeService->creer(['nom' => 'Comité pédagogique', 'description' => 'Groupe transverse']);
        $groupeService->ajouterMembres($groupe, [$enseignant->id]);

        $this->assertTrue($groupe->membres()->whereKey($enseignant->id)->exists());
        $this->assertTrue(
            ActivityLog::query()
                ->where('subject_type', $groupe->getMorphClass())
                ->where('subject_id', $groupe->id)
                ->exists()
        );
    }

    public function test_group_list_summarizes_members_and_granted_features(): void
    {
        $fondateur = User::factory()->create(['name' => 'Fondateur Test', 'statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $membre = User::factory()->create(['name' => 'Alice Comptable', 'statut' => 'actif']);
        $this->actingAs($fondateur);

        $groupe = app(GroupeServiceContract::class)->creer(['nom' => 'Caisse centrale']);
        app(GroupeServiceContract::class)->ajouterMembres($groupe, [$membre->id]);
        app(GroupeServiceContract::class)->accorderModulePourGroupe($groupe, 'module.finances');

        Livewire::test(ListGroupes::class)
            ->assertSee('Caisse centrale')
            ->assertSee('Alice Comptable')
            ->assertSee('Finances');
    }

    public function test_habilitation_catalog_covers_every_real_platform_module(): void
    {
        $codes = app(HabilitationServiceContract::class)
            ->getCatalogueParCategorie()
            ->flatten()
            ->pluck('code');

        foreach (['socle', 'rh', 'scolarite', 'pedagogie', 'finances', 'communication', 'assiduite', 'viescolaire', 'logistique'] as $module) {
            $this->assertTrue($codes->contains("module.{$module}"), "Le module {$module} manque au catalogue.");
        }
    }

    public function test_role_and_group_habilitation_changes_notify_impacted_users(): void
    {
        $directeur = User::factory()->create(['statut' => 'actif']);
        $directeur->assignRole('Directeur');
        $membre = User::factory()->create(['statut' => 'actif']);

        $notifications = Mockery::mock(NotificationServiceContract::class);
        $notifications->shouldReceive('envoyer')
            ->twice()
            ->withArgs(fn (string $canal, string $code, object $destinataire, array $donnees): bool =>
                $canal === 'in_app'
                && $code === 'habilitation_modifiee'
                && in_array($destinataire->id, [$directeur->id, $membre->id], true)
                && $donnees['code'] === 'module.rh'
                && $donnees['action'] === 'accordee'
            )
            ->andReturn((object) ['statut' => 'en_attente']);
        $this->app->instance(NotificationServiceContract::class, $notifications);
        // The catalogue seeder resolves the singleton before this test swaps
        // the notification port, so rebuild both audited services explicitly.
        $this->app->forgetInstance(HabilitationServiceContract::class);
        $this->app->forgetInstance(GroupeServiceContract::class);

        $groupe = app(GroupeServiceContract::class)->creer(['nom' => 'Direction pédagogique']);
        $groupe->membres()->attach($membre);

        app(HabilitationServiceContract::class)->activerModulePourRole('Directeur', 'module.rh');
        app(GroupeServiceContract::class)->accorderModulePourGroupe($groupe, 'module.rh');
    }

    public function test_permission_de_groupe_est_additive_et_ne_remplace_pas_les_permissions_du_role(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $comptable = User::factory()->create(['statut' => 'actif']);
        $comptable->assignRole('Comptable');
        $this->actingAs($fondateur);

        $habilitations = app(HabilitationServiceContract::class);
        $groupeService = app(GroupeServiceContract::class);

        // Le Comptable n'a normalement pas accès au module RH.
        $habilitations->desactiverModulePourRole('Comptable', 'module.rh', 'Comptable non concerné par le RH');
        $this->assertFalse($habilitations->moduleActifPourUser($comptable, 'module.rh'));
        $this->assertTrue($habilitations->moduleActifPourUser($comptable, 'module.finances'));

        $groupe = $groupeService->creer(['nom' => 'Référents RH', 'description' => null]);
        $groupeService->ajouterMembres($groupe, [$comptable->id]);
        $groupeService->accorderModulePourGroupe($groupe, 'module.rh');

        $this->assertTrue($habilitations->moduleActifPourUser($comptable, 'module.rh'));
        $this->assertTrue($habilitations->moduleActifPourUser($comptable, 'module.finances'));
    }

    public function test_multiple_groups_are_merged_and_the_most_permissive_grant_wins(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Directeur');
        $refuse = Groupe::query()->create(['nom' => 'Groupe restrictif']);
        $accorde = Groupe::query()->create(['nom' => 'Groupe autorisé']);
        $user->groupes()->attach([$refuse->id, $accorde->id]);

        $fonctionnalite = Fonctionnalite::query()->where('code', 'module.finances')->firstOrFail();
        $role = $user->roles()->where('name', 'Directeur')->firstOrFail();
        HabilitationRoleFonctionnalite::query()->updateOrCreate(
            ['role_id' => $role->id, 'fonctionnalite_id' => $fonctionnalite->id],
            ['actif' => false],
        );
        HabilitationGroupeFonctionnalite::query()->create([
            'groupe_id' => $refuse->id,
            'fonctionnalite_id' => $fonctionnalite->id,
            'actif' => false,
        ]);
        $habilitationAccordee = HabilitationGroupeFonctionnalite::query()->create([
            'groupe_id' => $accorde->id,
            'fonctionnalite_id' => $fonctionnalite->id,
            'actif' => true,
        ]);

        $service = app(HabilitationServiceContract::class);
        $this->assertTrue($service->moduleActifPourUser($user, 'module.finances'));

        $habilitationAccordee->update(['actif' => false]);
        $this->assertFalse($service->moduleActifPourUser($user, 'module.finances'));
    }

    public function test_permission_de_groupe_ouvre_le_module_et_ses_composants_filament(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $comptable = User::factory()->create(['statut' => 'actif']);
        $comptable->assignRole('Comptable');
        $this->actingAs($fondateur);

        $habilitations = app(HabilitationServiceContract::class);
        $habilitations->desactiverModulePourRole('Comptable', 'module.rh', 'Accès uniquement par groupe');

        $groupe = app(GroupeServiceContract::class)->creer(['nom' => 'Référents du personnel']);
        app(GroupeServiceContract::class)->ajouterMembres($groupe, [$comptable->id]);
        app(GroupeServiceContract::class)->accorderModulePourGroupe($groupe, 'module.rh');

        $this->assertTrue(ModuleAccess::canSeeModuleEntry($comptable, 'RH'));
        $this->assertTrue(ModuleAccess::canAccessComponent($comptable, EmployeResource::class));
    }

    public function test_synchronisation_des_membres_par_linterface_est_auditee(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $premier = User::factory()->create(['statut' => 'actif']);
        $second = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($fondateur);

        $service = app(GroupeServiceContract::class);
        $groupe = $service->creer(['nom' => 'Équipe de coordination']);
        $service->synchroniserMembres($groupe, [$premier->id]);
        $service->synchroniserMembres($groupe, [$second->id]);

        $this->assertEqualsCanonicalizing([$second->id], $groupe->membres()->pluck('users.id')->all());
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $groupe->getMorphClass(),
            'subject_id' => $groupe->id,
            'description' => 'Synchronisation des membres du groupe Équipe de coordination : +1 / -1',
        ]);
    }

    public function test_retrait_dune_permission_de_groupe_exige_un_motif(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        $groupeService = app(GroupeServiceContract::class);
        $groupe = $groupeService->creer(['nom' => 'Groupe test motif']);
        $groupeService->accorderModulePourGroupe($groupe, 'module.rh');

        $this->expectException(MotifRetraitPermissionGroupeRequisException::class);
        $groupeService->retirerModulePourGroupe($groupe, 'module.rh', '   ');
    }

    public function test_retrait_dune_permission_de_groupe_avec_motif_est_audite_sans_toucher_lacces_du_role(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $comptable = User::factory()->create(['statut' => 'actif']);
        $comptable->assignRole('Comptable');
        $this->actingAs($fondateur);

        $habilitations = app(HabilitationServiceContract::class);
        $groupeService = app(GroupeServiceContract::class);

        $habilitations->desactiverModulePourRole('Comptable', 'module.rh', 'Comptable non concerné par le RH');

        $groupe = $groupeService->creer(['nom' => 'Référents RH', 'description' => null]);
        $groupeService->ajouterMembres($groupe, [$comptable->id]);
        $groupeService->accorderModulePourGroupe($groupe, 'module.rh');
        $this->assertTrue($habilitations->moduleActifPourUser($comptable, 'module.rh'));

        $groupeService->retirerModulePourGroupe($groupe, 'module.rh', 'Attribution erronée');

        $this->assertFalse($habilitations->moduleActifPourUser($comptable, 'module.rh'));
        // Le retrait d'une permission de groupe ne touche jamais l'accès obtenu par le rôle.
        $this->assertTrue($habilitations->moduleActifPourUser($comptable, 'module.finances'));

        $habilitation = HabilitationGroupeFonctionnalite::query()
            ->where('groupe_id', $groupe->id)
            ->first();
        $log = ActivityLog::query()
            ->where('subject_type', $habilitation->getMorphClass())
            ->where('subject_id', $habilitation->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('Attribution erronée', $log->motif);
    }

    public function test_interface_des_permissions_de_groupe_ouvre_un_module_et_applique_les_changements(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        $groupe = app(GroupeServiceContract::class)->creer(['nom' => 'Équipe interface']);

        Livewire::test(ManageGroupePermissions::class)
            ->set('groupeSelectionne', $groupe->id)
            ->call('selectionnerModule', 'RH')
            ->assertSet('moduleSelectionne', 'RH')
            ->assertSee('Détail des fonctionnalités — Module RH')
            ->call('demanderChangement', 'module.rh', false)
            ->assertDispatched('platform-state-updated')
            ->assertSee('Accordée');

        $this->assertDatabaseHas('habilitations_groupes_fonctionnalites', [
            'groupe_id' => $groupe->id,
            'actif' => true,
        ]);

        Livewire::test(ManageGroupePermissions::class)
            ->set('groupeSelectionne', $groupe->id)
            ->call('demanderChangement', 'module.rh', true)
            ->set('motif', 'Accès retiré après contrôle')
            ->call('confirmerRetrait')
            ->assertDispatched('platform-state-updated');

        $this->assertDatabaseHas('habilitations_groupes_fonctionnalites', [
            'groupe_id' => $groupe->id,
            'actif' => false,
            'dernier_motif' => 'Accès retiré après contrôle',
        ]);
    }

    public function test_habilitations_de_roles_et_groupes_partagent_une_seule_entree_de_navigation(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        $this->assertCount(1, ManageHabilitations::getNavigationItems());
        $this->assertTrue(ManageHabilitations::shouldRegisterNavigation());
        $this->assertFalse(ManageGroupePermissions::shouldRegisterNavigation());

        $this->get(ManageHabilitations::getUrl())
            ->assertSuccessful()
            ->assertSee('Rôles du personnel')
            ->assertSee('Groupes');

        $this->get(ManageGroupePermissions::getUrl())
            ->assertSuccessful()
            ->assertSee('Rôles du personnel')
            ->assertSee('Groupes');
    }
}
