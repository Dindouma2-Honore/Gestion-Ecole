<?php

declare(strict_types=1);

namespace App\Modules\Technique\Services;

use App\Models\User;
use App\Modules\Socle\Contracts\HabilitationServiceContract;
use Throwable;

final class TechnicalAccess
{
    public static function allows(?User $user, string $permission = 'technique.view'): bool
    {
        if (! $user || $user->statut !== 'actif') {
            return false;
        }
        try {
            return app(HabilitationServiceContract::class)->moduleActifPourUser($user, 'module.technique')
                && ($user->hasRole('Fondateur') || $user->hasPermissionTo($permission));
        } catch (Throwable) {
            return false;
        }
    }
}
