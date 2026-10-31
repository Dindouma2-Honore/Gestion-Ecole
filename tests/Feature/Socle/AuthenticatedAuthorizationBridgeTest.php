<?php

declare(strict_types=1);

namespace Tests\Feature\Socle;

use App\Models\User;
use App\Modules\Socle\Contracts\AuthenticatedAuthorizationContract;
use App\Modules\Socle\Contracts\AuthorizationContract;
use App\Modules\Socle\Contracts\AuthorizationPositionResolverContract;
use App\Modules\Socle\Data\AuthorizationSubjectData;
use App\Modules\Socle\Enums\AuthorizationAction;
use App\Modules\Socle\Enums\AuthorizationSubjectType;
use App\Modules\Socle\Models\AuthorizationGrant;
use App\Modules\Socle\Models\Fonctionnalite;
use App\Modules\Socle\Models\Groupe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticatedAuthorizationBridgeTest extends TestCase
{
    use RefreshDatabase;

    private function feature(string $suffix): Fonctionnalite
    {
        return Fonctionnalite::query()->create([
            'code' => "bridge_auth.{$suffix}",
            'nom' => "Bridge {$suffix}",
            'categorie' => 'Tests',
            'module_technique' => 'Tests',
            'ordre' => 998,
            'actif' => true,
        ]);
    }

    private function bridge(): AuthenticatedAuthorizationContract
    {
        return app(AuthenticatedAuthorizationContract::class);
    }

    private function grant(string $type, int $subjectId, string $featureCode, AuthorizationAction ...$actions): void
    {
        app(AuthorizationContract::class)->grant(
            new AuthorizationSubjectData(AuthorizationSubjectType::from($type), $subjectId),
            $featureCode,
            ...$actions,
        );
    }

    public function test_guest_is_denied_on_every_action(): void
    {
        $feature = $this->feature('guest');
        $bridge = $this->bridge();

        $this->assertFalse($bridge->canRead($feature->code));
        $this->assertFalse($bridge->canWrite($feature->code));
        $this->assertFalse($bridge->canExecute($feature->code));
        $this->assertFalse($bridge->canPrint($feature->code));
    }

    public function test_authenticated_user_with_user_read_grant_can_read(): void
    {
        $user = User::factory()->create();
        $feature = $this->feature('user_read');
        $this->actingAs($user);
        $this->grant('user', $user->id, $feature->code, AuthorizationAction::READ);

        $this->assertTrue($this->bridge()->canRead($feature->code));
        $this->assertFalse($this->bridge()->canWrite($feature->code));
        $this->assertFalse($this->bridge()->canExecute($feature->code));
        $this->assertFalse($this->bridge()->canPrint($feature->code));
    }

    public function test_authenticated_user_without_grant_is_denied(): void
    {
        $user = User::factory()->create();
        $feature = $this->feature('no_grant');
        $this->actingAs($user);

        $this->assertFalse($this->bridge()->canRead($feature->code));
        $this->assertFalse($this->bridge()->canWrite($feature->code));
        $this->assertFalse($this->bridge()->canExecute($feature->code));
        $this->assertFalse($this->bridge()->canPrint($feature->code));
    }

    public function test_group_grant_is_exposed_through_the_bridge(): void
    {
        $user = User::factory()->create();
        $group = Groupe::query()->create(['nom' => 'Groupe bridge test']);
        $group->membres()->attach($user->id);
        $feature = $this->feature('groupe');
        $this->actingAs($user);
        $this->grant('group', $group->id, $feature->code, AuthorizationAction::READ);

        $this->assertTrue($this->bridge()->canRead($feature->code));
        $this->assertFalse($this->bridge()->canWrite($feature->code));
    }

    public function test_rh_position_grant_is_exposed_through_the_bridge(): void
    {
        $user = User::factory()->create();
        $feature = $this->feature('position');
        app()->bind(AuthorizationPositionResolverContract::class, fn (): AuthorizationPositionResolverContract => new class implements AuthorizationPositionResolverContract
        {
            public function positionIdsForUser(int $userId): array
            {
                return [777];
            }
        });
        $this->grant('rh_position', 777, $feature->code, AuthorizationAction::EXECUTE);
        $this->actingAs($user);

        $bridge = $this->bridge();
        $this->assertTrue($bridge->canExecute($feature->code));
        $this->assertFalse($bridge->canRead($feature->code));
        $this->assertFalse($bridge->canWrite($feature->code));
        $this->assertFalse($bridge->canPrint($feature->code));
    }

    public function test_write_execute_print_actions_delegate_to_the_v1_engine(): void
    {
        $user = User::factory()->create();
        $writeFeature = $this->feature('write');
        $executeFeature = $this->feature('execute');
        $printFeature = $this->feature('print');
        $this->actingAs($user);
        $this->grant('user', $user->id, $writeFeature->code, AuthorizationAction::WRITE);
        $this->grant('user', $user->id, $executeFeature->code, AuthorizationAction::EXECUTE);
        $this->grant('user', $user->id, $printFeature->code, AuthorizationAction::PRINT);

        $bridge = $this->bridge();
        $this->assertTrue($bridge->canWrite($writeFeature->code));
        $this->assertFalse($bridge->canRead($writeFeature->code));
        $this->assertTrue($bridge->canExecute($executeFeature->code));
        $this->assertFalse($bridge->canWrite($executeFeature->code));
        $this->assertTrue($bridge->canPrint($printFeature->code));
        $this->assertFalse($bridge->canRead($printFeature->code));
    }

    public function test_reading_does_not_create_any_grant(): void
    {
        $user = User::factory()->create();
        $feature = $this->feature('lecture_seule');
        $this->actingAs($user);

        $before = AuthorizationGrant::query()->count();
        $this->bridge()->canRead($feature->code);
        $this->bridge()->canWrite($feature->code);
        $this->bridge()->canExecute($feature->code);
        $this->bridge()->canPrint($feature->code);

        $this->assertSame($before, AuthorizationGrant::query()->count());
    }

    public function test_fondateur_keeps_strict_v1_behaviour_without_central_bypass(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'Fondateur', 'guard_name' => 'web']);
        $user->assignRole('Fondateur');
        $feature = $this->feature('fondateur_strict');
        $this->actingAs($user);

        $this->assertFalse($this->bridge()->canRead($feature->code));
    }
}
