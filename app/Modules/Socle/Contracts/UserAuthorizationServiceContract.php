<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use App\Models\User;

interface UserAuthorizationServiceContract
{
    /** L'utilisateur a-t-il ce rôle ? */
    public function possedeRole(User $user, string $role): bool;

    /** L'utilisateur a-t-il accès à ce niveau (via son scope) ? */
    public function aAccesAuNiveau(User $user, int $niveauId): bool;

    /** Retourne le rôle validateur requis pour un montant donné dans une catégorie de dépense */
    public function validateurRequisPour(int $categorieDepenseId, float $montant): string;

    /** L'utilisateur peut-il valider financièrement ce montant ? */
    public function peutValiderDepense(User $user, int $categorieDepenseId, float $montant): bool;
}
