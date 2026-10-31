<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Socle\Contracts\AuthorizationContract;
use App\Modules\Socle\Contracts\AuthorizationPositionResolverContract;
use App\Modules\Socle\Data\AuthorizationSubjectData;
use App\Modules\Socle\Enums\AuthorizationAction;
use App\Modules\Socle\Enums\AuthorizationSubjectType;
use App\Modules\Socle\Models\AuthorizationGrant;
use App\Modules\Socle\Models\Fonctionnalite;
use App\Modules\Socle\Models\Groupe;

function authorizationFeature(string $suffix): Fonctionnalite
{
    return Fonctionnalite::query()->create([
        'code' => "test_auth.{$suffix}",
        'nom' => "Fonctionnalité {$suffix}",
        'categorie' => 'Tests',
        'module_technique' => 'Tests',
        'ordre' => 999,
        'actif' => true,
    ]);
}

function authorizationService(): AuthorizationContract
{
    return app(AuthorizationContract::class);
}

it('grants a user action without granting the other actions', function (): void {
    $user = User::factory()->create();
    $feature = authorizationFeature('user');

    authorizationService()->grant(
        new AuthorizationSubjectData(AuthorizationSubjectType::USER, $user->id),
        $feature->code,
        AuthorizationAction::WRITE,
    );

    expect(authorizationService()->canWrite($user->id, $feature->code))->toBeTrue()
        ->and(authorizationService()->canRead($user->id, $feature->code))->toBeFalse()
        ->and(authorizationService()->canExecute($user->id, $feature->code))->toBeFalse()
        ->and(authorizationService()->canPrint($user->id, $feature->code))->toBeFalse();
});

it('aggregates a group grant for its member', function (): void {
    $user = User::factory()->create();
    $group = Groupe::query()->create(['nom' => 'Groupe test autorisation']);
    $group->membres()->attach($user->id);
    $feature = authorizationFeature('groupe');

    authorizationService()->grant(
        new AuthorizationSubjectData(AuthorizationSubjectType::GROUP, $group->id),
        $feature->code,
        AuthorizationAction::READ,
    );

    expect(authorizationService()->canRead($user->id, $feature->code))->toBeTrue();
});

it('aggregates an RH position through its public resolver abstraction', function (): void {
    $user = User::factory()->create();
    $feature = authorizationFeature('position');
    app()->bind(AuthorizationPositionResolverContract::class, fn (): AuthorizationPositionResolverContract => new class implements AuthorizationPositionResolverContract
    {
        public function positionIdsForUser(int $userId): array
        {
            return [418];
        }
    });

    authorizationService()->grant(
        new AuthorizationSubjectData(AuthorizationSubjectType::RH_POSITION, 418),
        $feature->code,
        AuthorizationAction::EXECUTE,
    );

    expect(authorizationService()->canExecute($user->id, $feature->code))->toBeTrue();
});

it('cumulates positive grants from user group and RH position', function (): void {
    $user = User::factory()->create();
    $group = Groupe::query()->create(['nom' => 'Groupe cumul autorisation']);
    $group->membres()->attach($user->id);
    $feature = authorizationFeature('cumul');
    app()->bind(AuthorizationPositionResolverContract::class, fn (): AuthorizationPositionResolverContract => new class implements AuthorizationPositionResolverContract
    {
        public function positionIdsForUser(int $userId): array
        {
            return [419];
        }
    });

    $service = authorizationService();
    $service->grant(new AuthorizationSubjectData(AuthorizationSubjectType::USER, $user->id), $feature->code, AuthorizationAction::PRINT);
    $service->grant(new AuthorizationSubjectData(AuthorizationSubjectType::GROUP, $group->id), $feature->code, AuthorizationAction::WRITE);
    $service->grant(new AuthorizationSubjectData(AuthorizationSubjectType::RH_POSITION, 419), $feature->code, AuthorizationAction::READ);

    expect($service->canRead($user->id, $feature->code))->toBeTrue()
        ->and($service->canWrite($user->id, $feature->code))->toBeTrue()
        ->and($service->canExecute($user->id, $feature->code))->toBeFalse()
        ->and($service->canPrint($user->id, $feature->code))->toBeTrue();
});

it('does not use an inactive group grant', function (): void {
    $user = User::factory()->create();
    $group = Groupe::query()->create(['nom' => 'Groupe inactif autorisation']);
    $group->membres()->attach($user->id);
    $feature = authorizationFeature('groupe_inactif');

    AuthorizationGrant::query()->create([
        'subject_type' => AuthorizationSubjectType::GROUP,
        'subject_id' => $group->id,
        'feature_id' => $feature->id,
        'can_read' => true,
        'is_active' => false,
    ]);

    expect(authorizationService()->canRead($user->id, $feature->code))->toBeFalse();
});

it('denies access when no positive grant exists', function (): void {
    $user = User::factory()->create();
    $feature = authorizationFeature('absence');

    expect(authorizationService()->decide($user->id, $feature->code, AuthorizationAction::READ)->allowed)->toBeFalse();
});

it('aggregates grants carried by several distinct RH positions of the same user', function (): void {
    $user = User::factory()->create();
    $feature = authorizationFeature('multi_positions');
    app()->bind(AuthorizationPositionResolverContract::class, fn (): AuthorizationPositionResolverContract => new class implements AuthorizationPositionResolverContract
    {
        public function positionIdsForUser(int $userId): array
        {
            return [501, 502];
        }
    });

    $service = authorizationService();
    $service->grant(
        new AuthorizationSubjectData(AuthorizationSubjectType::RH_POSITION, 502),
        $feature->code,
        AuthorizationAction::READ,
    );

    expect($service->canRead($user->id, $feature->code))->toBeTrue()
        ->and($service->canWrite($user->id, $feature->code))->toBeFalse()
        ->and($service->canExecute($user->id, $feature->code))->toBeFalse();

    $service->grant(
        new AuthorizationSubjectData(AuthorizationSubjectType::RH_POSITION, 501),
        $feature->code,
        AuthorizationAction::EXECUTE,
    );

    expect($service->canExecute($user->id, $feature->code))->toBeTrue()
        ->and($service->canRead($user->id, $feature->code))->toBeTrue()
        ->and($service->canWrite($user->id, $feature->code))->toBeFalse();
});
