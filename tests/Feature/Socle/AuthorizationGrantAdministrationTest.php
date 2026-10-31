<?php

declare(strict_types=1);

namespace Tests\Feature\Socle;

use App\Models\User;
use App\Modules\RH\Contracts\PosteReadContract;
use App\Modules\RH\Data\PosteReadDTO;
use App\Modules\Socle\Contracts\AuthenticatedAuthorizationContract;
use App\Modules\Socle\Enums\AuthorizationSubjectType;
use App\Modules\Socle\Filament\Pages\ManageAuthorizationGrants;
use App\Modules\Socle\Models\AuthorizationGrant;
use App\Modules\Socle\Models\Fonctionnalite;
use App\Modules\Socle\Models\Groupe;
use App\Modules\Socle\Services\AuthorizationGrantService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationGrantAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function feature(string $suffix): Fonctionnalite
    {
        return Fonctionnalite::query()->create([
            'code' => "grant_admin.{$suffix}",
            'nom' => "Fonctionnalité grant {$suffix}",
            'categorie' => 'Tests',
            'module_technique' => 'Tests',
            'ordre' => 997,
            'actif' => true,
        ]);
    }

    private function service(): AuthorizationGrantService
    {
        return app(AuthorizationGrantService::class);
    }

    private function bridge(): AuthenticatedAuthorizationContract
    {
        return app(AuthenticatedAuthorizationContract::class);
    }

    private function activeUser(): User
    {
        return User::factory()->create(['statut' => 'actif', 'must_change_password' => false]);
    }

    private function stubPosteReadContract(int $posteId = 42): void
    {
        app()->bind(PosteReadContract::class, fn (): PosteReadContract => new class($posteId) implements PosteReadContract
        {
            public function __construct(private readonly int $posteId) {}

            public function read(int $posteId): ?array
            {
                return null;
            }

            public function readAll(): Collection
            {
                return collect([new PosteReadDTO(
                    id: $this->posteId,
                    nom: 'Directeur des études',
                    departement: 'Direction',
                    niveau_hierarchique: 1,
                    ordre: 1,
                    actif: true,
                    parent_id: null,
                    enfants: null,
                    employes_count: 0,
                )]);
            }

            public function readByDepartement(?string $departement): Collection
            {
                return collect();
            }

            public function organigramme(): Collection
            {
                return collect();
            }
        });
    }

    public function test_user_grant_is_created(): void
    {
        $user = $this->activeUser();
        $feature = $this->feature('user');

        $this->service()->enregistrer('user', $user->id, $feature->code, ['read']);

        $grant = AuthorizationGrant::query()->sole();
        $this->assertSame(AuthorizationSubjectType::USER, $grant->subject_type);
        $this->assertSame($user->id, $grant->subject_id);
        $this->assertTrue($grant->can_read);
        $this->assertTrue($grant->is_active);
    }

    public function test_group_grant_is_created(): void
    {
        $feature = $this->feature('group');
        $group = Groupe::query()->create(['nom' => 'Groupe grants V1']);

        $this->service()->enregistrer('group', $group->id, $feature->code, ['read']);

        $grant = AuthorizationGrant::query()->sole();
        $this->assertSame(AuthorizationSubjectType::GROUP, $grant->subject_type);
        $this->assertSame($group->id, $grant->subject_id);
    }

    public function test_rh_position_grant_is_created_through_poste_read_contract(): void
    {
        $this->stubPosteReadContract(42);
        $feature = $this->feature('position');

        $options = $this->service()->optionsSujets('rh_position');
        $this->assertSame([42 => 'Directeur des études'], $options);

        $this->service()->enregistrer('rh_position', 42, $feature->code, ['read']);

        $grant = AuthorizationGrant::query()->sole();
        $this->assertSame(AuthorizationSubjectType::RH_POSITION, $grant->subject_type);
        $this->assertSame(42, $grant->subject_id);
    }

    public function test_all_four_actions_are_persisted(): void
    {
        $user = $this->activeUser();
        $feature = $this->feature('actions');
        $this->actingAs($user);

        $this->service()->enregistrer('user', $user->id, $feature->code, ['read', 'write', 'execute', 'print']);

        $grant = AuthorizationGrant::query()->sole();
        $this->assertTrue($grant->can_read);
        $this->assertTrue($grant->can_write);
        $this->assertTrue($grant->can_execute);
        $this->assertTrue($grant->can_print);

        $this->assertTrue($this->bridge()->canRead($feature->code));
        $this->assertTrue($this->bridge()->canWrite($feature->code));
        $this->assertTrue($this->bridge()->canExecute($feature->code));
        $this->assertTrue($this->bridge()->canPrint($feature->code));
    }

    public function test_updating_an_existing_grant_does_not_duplicate_it(): void
    {
        $user = $this->activeUser();
        $feature = $this->feature('maj');
        $service = $this->service();

        $service->enregistrer('user', $user->id, $feature->code, ['read']);
        $service->enregistrer('user', $user->id, $feature->code, ['read', 'write']);

        $this->assertSame(1, AuthorizationGrant::query()->count());
        $grant = AuthorizationGrant::query()->sole();
        $this->assertTrue($grant->can_read);
        $this->assertTrue($grant->can_write);

        // Retrait explicite d'une action non cochée (grant() étant additif).
        $service->enregistrer('user', $user->id, $feature->code, ['write']);

        $this->assertSame(1, AuthorizationGrant::query()->count());
        $grant = AuthorizationGrant::query()->sole();
        $this->assertFalse($grant->can_read);
        $this->assertTrue($grant->can_write);
    }

    public function test_deactivation_is_respected_and_keeps_the_row(): void
    {
        $user = $this->activeUser();
        $feature = $this->feature('inactif');
        $this->actingAs($user);
        $service = $this->service();

        $service->enregistrer('user', $user->id, $feature->code, ['read']);
        $this->assertTrue($this->bridge()->canRead($feature->code));

        $grant = AuthorizationGrant::query()->sole();
        $service->desactiver($grant->id);

        $this->assertSame(1, AuthorizationGrant::query()->count());
        $this->assertFalse(AuthorizationGrant::query()->sole()->is_active);
        $this->assertFalse($this->bridge()->canRead($feature->code));

        $service->reactiver($grant->id);
        $this->assertTrue(AuthorizationGrant::query()->sole()->is_active);
        $this->assertTrue($this->bridge()->canRead($feature->code));
    }

    public function test_unknown_feature_is_refused(): void
    {
        $user = $this->activeUser();

        $this->expectException(ValidationException::class);
        $this->service()->enregistrer('user', $user->id, 'grant_admin.inexistante', ['read']);
    }

    public function test_unknown_subject_is_refused(): void
    {
        $feature = $this->feature('sujet');

        $this->expectException(ValidationException::class);
        $this->service()->enregistrer('user', 999999, $feature->code, ['read']);
    }

    public function test_role_subject_type_is_refused(): void
    {
        $feature = $this->feature('role');
        $this->assertNull(AuthorizationSubjectType::tryFrom('role'));

        $this->expectException(ValidationException::class);
        $this->service()->enregistrer('role', 1, $feature->code, ['read']);
    }

    public function test_unauthorized_user_cannot_administer_grants(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $simple = $this->activeUser();
        $this->actingAs($simple);
        $this->assertFalse(ManageAuthorizationGrants::canAccess());
        $this->actingAs($simple)->get(ManageAuthorizationGrants::getUrl())->assertForbidden();

        $admin = $this->activeUser();
        $admin->assignRole('Fondateur');
        $this->actingAs($admin);
        $this->assertTrue(ManageAuthorizationGrants::canAccess());
        $this->actingAs($admin)->get(ManageAuthorizationGrants::getUrl())->assertSuccessful();
    }

    public function test_administration_writes_neither_legacy_habilitations_nor_spatie_tables(): void
    {
        $user = $this->activeUser();
        $feature = $this->feature('integrite');
        $service = $this->service();

        $legacyTables = [
            'habilitations_roles_fonctionnalites',
            'habilitations_groupes_fonctionnalites',
            'habilitations_users_fonctionnalites',
        ];
        $spatieCounts = fn (): array => [
            Permission::query()->count(),
            Role::query()->count(),
            DB::table('model_has_permissions')->count(),
            DB::table('role_has_permissions')->count(),
            DB::table('model_has_roles')->count(),
        ];

        $legacyBefore = array_map(fn (string $table): int => DB::table($table)->count(), $legacyTables);
        $spatieBefore = $spatieCounts();

        $service->enregistrer('user', $user->id, $feature->code, ['read', 'write']);
        $service->enregistrer('user', $user->id, $feature->code, ['read']);
        $grant = AuthorizationGrant::query()->sole();
        $service->desactiver($grant->id);
        $service->reactiver($grant->id);
        $service->lister();

        $legacyAfter = array_map(fn (string $table): int => DB::table($table)->count(), $legacyTables);
        $spatieAfter = $spatieCounts();

        $this->assertSame($legacyBefore, $legacyAfter);
        $this->assertSame($spatieBefore, $spatieAfter);
        $this->assertSame(1, AuthorizationGrant::query()->count());
    }

    public function test_ui_enregistrement_flow_reaches_the_bridge_decision(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = $this->activeUser();
        $admin->assignRole('Fondateur');
        $target = $this->activeUser();
        $feature = $this->feature('ui');

        $this->actingAs($admin);
        Livewire::test(ManageAuthorizationGrants::class)
            ->set('data.type_sujet', 'user')
            ->set('data.sujet_id', $target->id)
            ->set('data.feature_code', $feature->code)
            ->set('data.actions', ['read'])
            ->call('enregistrer');

        $this->assertSame(1, AuthorizationGrant::query()->count());

        $this->actingAs($target);
        $this->assertTrue($this->bridge()->canRead($feature->code));
        $this->assertFalse($this->bridge()->canWrite($feature->code));
    }
}
