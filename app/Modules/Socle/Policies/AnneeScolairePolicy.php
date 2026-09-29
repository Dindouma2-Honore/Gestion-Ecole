<?php

declare(strict_types=1);

namespace App\Modules\Socle\Policies;

use App\Models\User;
use App\Modules\Socle\Contracts\ModulePolicy;
use App\Modules\Socle\Models\AnneeScolaire;

class AnneeScolairePolicy implements ModulePolicy
{
    public static function rolesAutorises(): array
    {
        return ['Fondateur'];
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('Fondateur');
    }

    public function view(User $user, AnneeScolaire $annee): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AnneeScolaire $annee): bool
    {
        return $this->viewAny($user) && $annee->statut !== AnneeScolaire::STATUT_ARCHIVEE;
    }

    public function delete(User $user, AnneeScolaire $annee): bool
    {
        return $this->viewAny($user) && $annee->statut === AnneeScolaire::STATUT_BROUILLON;
    }
}
