<?php

declare(strict_types=1);

namespace App\Modules\Socle\Policies;

use App\Models\User;
use App\Modules\Socle\Contracts\ModulePolicy;
use Illuminate\Database\Eloquent\Model;

class ParametragePolicy implements ModulePolicy
{
    public static function rolesAutorises(): array
    {
        return ['Fondateur'];
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Fondateur');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }
}
