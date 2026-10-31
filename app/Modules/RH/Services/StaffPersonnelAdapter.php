<?php

declare(strict_types=1);

namespace App\Modules\RH\Services;

use App\Modules\RH\Models\Employe;
use App\Modules\Socle\Contracts\AuthorizationPositionResolverContract;
use App\Modules\Socle\Contracts\StaffPersonnelContract;
use App\Modules\Socle\Data\StaffPersonnelData;

final class StaffPersonnelAdapter implements AuthorizationPositionResolverContract, StaffPersonnelContract
{
    public function activeForAccount(int $id): ?StaffPersonnelData
    {
        $e = Employe::query()->lockForUpdate()->find($id);

        return $e && $e->statut === 'actif' ? new StaffPersonnelData($e->id, $e->nom, $e->prenom, $e->telephone, $e->statut, $e->user_id, $e->role?->name, $e->niveau_id) : null;
    }

    public function linkUser(int $id, int $userId): void
    {
        Employe::query()->whereKey($id)->update(['user_id' => $userId]);
    }

    public function positionIdsForUser(int $userId): array
    {
        return Employe::query()->where('user_id', $userId)->where('statut', 'actif')->whereNotNull('poste_administratif_id')->pluck('poste_administratif_id')->map(fn ($id) => (int) $id)->all();
    }
}
