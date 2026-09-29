<?php

declare(strict_types=1);

namespace App\Modules\Socle\Policies;

use App\Models\User;
use App\Modules\Socle\Contracts\ModulePolicy;

class UserPolicy implements ModulePolicy
{
    public static function rolesAutorises(): array
    {
        return ['Fondateur'];
    }

    public function before(User $user): ?bool
    {
        return $user->hasRole('Fondateur') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('gerer_utilisateurs');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('gerer_utilisateurs');
    }

    public function create(User $user): bool
    {
        return $user->can('gerer_utilisateurs');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('gerer_utilisateurs');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('gerer_utilisateurs') && $user->isNot($model);
    }
}
