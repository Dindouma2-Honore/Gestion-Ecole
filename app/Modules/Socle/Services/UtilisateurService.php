<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Models\User;
use App\Modules\Socle\Contracts\UtilisateurServiceInterface;

/**
 * Implémentation de référence. Suppose que le modèle User (celui livré
 * par défaut par Laravel, dans app/Models/User.php) utilise le trait
 * Spatie\Permission\Traits\HasRoles — voir composer.json (spatie/laravel-permission).
 */
class UtilisateurService implements UtilisateurServiceInterface
{
    public function existe(int $utilisateurId): bool
    {
        return User::query()->whereKey($utilisateurId)->exists();
    }

    public function roles(int $utilisateurId): array
    {
        $user = User::query()->findOrFail($utilisateurId);

        return $user->getRoleNames()->all();
    }

    public function possedePermission(int $utilisateurId, string $permission): bool
    {
        $user = User::query()->findOrFail($utilisateurId);

        return $user->can($permission);
    }
}
