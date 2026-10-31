<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Modules\Socle\Contracts\AuthenticatedAuthorizationContract;
use App\Modules\Socle\Contracts\AuthorizationContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Couche d'intégration Auth -> Authorization V1.
 * Délègue intégralement au moteur AuthorizationContract (moteur pur userId).
 * Un visiteur non authentifié (guest) est toujours refusé (DENY).
 */
final class AuthenticatedAuthorizationService implements AuthenticatedAuthorizationContract
{
    public function __construct(
        private readonly AuthorizationContract $authorization,
        private readonly AuthFactory $auth,
    ) {}

    public function canRead(string $featureCode): bool
    {
        return $this->decide(fn (int $userId): bool => $this->authorization->canRead($userId, $featureCode));
    }

    public function canWrite(string $featureCode): bool
    {
        return $this->decide(fn (int $userId): bool => $this->authorization->canWrite($userId, $featureCode));
    }

    public function canExecute(string $featureCode): bool
    {
        return $this->decide(fn (int $userId): bool => $this->authorization->canExecute($userId, $featureCode));
    }

    public function canPrint(string $featureCode): bool
    {
        return $this->decide(fn (int $userId): bool => $this->authorization->canPrint($userId, $featureCode));
    }

    private function decide(callable $decision): bool
    {
        $userId = $this->userId();

        if ($userId === null) {
            return false;
        }

        return (bool) $decision($userId);
    }

    private function userId(): ?int
    {
        $user = $this->auth->guard()->user();

        if (! $user instanceof Authenticatable) {
            return null;
        }

        $identifier = $user->getAuthIdentifier();

        return $identifier === null ? null : (int) $identifier;
    }
}
