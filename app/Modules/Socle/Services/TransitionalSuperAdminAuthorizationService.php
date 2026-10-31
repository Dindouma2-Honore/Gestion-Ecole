<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Models\User;
use App\Modules\Socle\Contracts\AuthenticatedAuthorizationContract;
use App\Modules\Socle\Contracts\TransitionalSuperAdminAuthorizationContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Bypass super-admin centralisé et explicitement transitionnel.
 *
 * Flux : consommateur -> couche transitionnelle -> AuthenticatedAuthorizationContract
 *       -> AuthorizationContract -> AuthorizationService (strict, sans Spatie).
 *
 * Seule la couche transitionnelle connaît Spatie : le moteur V1, le bridge Auth
 * et les consommateurs restent indépendants des rôles. Le vrai grant V1 est
 * donc la source de vérité pour tout utilisateur non super-admin.
 */
final class TransitionalSuperAdminAuthorizationService implements TransitionalSuperAdminAuthorizationContract
{
    /**
     * Rôles super-admin déjà reconnus par l'application existante
     * (ModuleAccess::canAccessModule et les pages d'administration V1)
     * — aucun rôle inventé.
     */
    public const ROLES_SUPER_ADMIN = ['Fondateur', 'Admin', 'Administrateur', 'Super Admin'];

    public function __construct(
        private readonly AuthenticatedAuthorizationContract $authorization,
        private readonly AuthFactory $auth,
    ) {}

    public function canRead(string $featureCode): bool
    {
        return $this->decide($featureCode, fn (string $code): bool => $this->authorization->canRead($code));
    }

    public function canWrite(string $featureCode): bool
    {
        return $this->decide($featureCode, fn (string $code): bool => $this->authorization->canWrite($code));
    }

    public function canExecute(string $featureCode): bool
    {
        return $this->decide($featureCode, fn (string $code): bool => $this->authorization->canExecute($code));
    }

    public function canPrint(string $featureCode): bool
    {
        return $this->decide($featureCode, fn (string $code): bool => $this->authorization->canPrint($code));
    }

    /** @param  callable(string): bool  $decision */
    private function decide(string $featureCode, callable $decision): bool
    {
        if ($this->estSuperAdminTransitionnel()) {
            return true;
        }

        return (bool) $decision($featureCode);
    }

    private function estSuperAdminTransitionnel(): bool
    {
        $user = $this->auth->guard()->user();

        if (! $user instanceof User || ! $user instanceof Authenticatable) {
            return false;
        }

        return $user->hasAnyRole(self::ROLES_SUPER_ADMIN);
    }
}
