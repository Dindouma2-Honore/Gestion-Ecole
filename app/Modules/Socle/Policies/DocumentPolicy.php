<?php

declare(strict_types=1);

namespace App\Modules\Socle\Policies;

use App\Models\User;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Models\Document;

class DocumentPolicy
{
    public function __construct(private readonly DocumentServiceContract $documents) {}

    public function viewAny(User $user): bool
    {
        return $user->statut === 'actif';
    }

    public function view(User $user, Document $document): bool
    {
        return $this->documents->peutAcceder($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->statut === 'actif';
    }

    public function update(User $user, Document $document): bool
    {
        return $user->statut === 'actif';
    }

    public function delete(User $user, Document $document): bool
    {
        return $document->categorie !== 'recu_paiement' && $user->hasAnyRole(['Fondateur', 'Directeur', 'Admin', 'Administrateur']);
    }
}
