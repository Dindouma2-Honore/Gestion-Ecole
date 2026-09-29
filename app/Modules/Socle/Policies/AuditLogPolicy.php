<?php

declare(strict_types=1);

namespace App\Modules\Socle\Policies;

use App\Models\User;
use App\Modules\Socle\Contracts\ModulePolicy;
use App\Modules\Socle\Models\ActivityLog;

class AuditLogPolicy implements ModulePolicy
{
    public static function rolesAutorises(): array
    {
        return ['Fondateur'];
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Fondateur');
    }

    public function view(User $user, ActivityLog $activity): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ActivityLog $activity): bool
    {
        return false;
    }

    public function delete(User $user, ActivityLog $activity): bool
    {
        return false;
    }
}
