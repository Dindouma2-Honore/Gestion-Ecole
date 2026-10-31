<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

/**
 * Couche de compatibilité super-admin TRANSITIONNELLE, positionnée AU-DESSUS
 * d'AuthenticatedAuthorizationContract (donc AU-DESSUS du moteur V1 strict).
 *
 * Permet à un Fondateur / super-admin déjà autorisé par l'application legacy
 * de ne pas perdre l'accès à un consommateur migré avant que les grants V1
 * aient été configurés. À supprimer en fin de transition (aucun autre rôle,
 * aucune permission Spatie et aucune habilitation legacy n'est créée).
 */
interface TransitionalSuperAdminAuthorizationContract
{
    public function canRead(string $featureCode): bool;

    public function canWrite(string $featureCode): bool;

    public function canExecute(string $featureCode): bool;

    public function canPrint(string $featureCode): bool;
}
