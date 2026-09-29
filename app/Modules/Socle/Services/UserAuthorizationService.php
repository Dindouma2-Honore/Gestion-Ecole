<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Models\User;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use App\Modules\Socle\Contracts\UserAuthorizationServiceContract;

class UserAuthorizationService implements UserAuthorizationServiceContract
{
    public function __construct(
        private ?ParametrageServiceContract $parametrageService = null
    ) {}

    public function possedeRole(User $user, string $role): bool
    {
        return $user->hasRole($role);
    }

    public function aAccesAuNiveau(User $user, int $niveauId): bool
    {
        if ($user->hasAnyRole(['Fondateur', 'Comptable', 'SurveillantGeneral', 'ChargeLogistique'])) {
            return true;
        }

        if ($user->hasAnyRole(['Directeur', 'Enseignant'])) {
            return $user->niveau_id !== null && (int) $user->niveau_id === $niveauId;
        }

        return false;
    }

    public function validateurRequisPour(int $categorieDepenseId, float $montant): string
    {
        if ($this->parametrageService) {
            return $this->parametrageService->getValidateurRequis($categorieDepenseId, $montant);
        }

        return 'Fondateur';
    }

    public function peutValiderDepense(User $user, int $categorieDepenseId, float $montant): bool
    {
        $roleRequis = $this->validateurRequisPour($categorieDepenseId, $montant);

        return $user->hasRole($roleRequis) || $user->hasRole('Fondateur');
    }
}
