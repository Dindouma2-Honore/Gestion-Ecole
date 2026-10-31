<?php

declare(strict_types=1);

namespace App\Modules\RH\Services;

use App\Modules\RH\Contracts\PosteReadContract;
use App\Modules\RH\Data\PosteOccupantReadDTO;
use App\Modules\RH\Data\PosteReadDTO;
use App\Modules\RH\Models\PosteAdministratif;
use Illuminate\Support\Collection;

class PosteReadService implements PosteReadContract
{
    public function read(int $posteId): array
    {
        $poste = PosteAdministratif::find($posteId);

        if (! $poste) {
            return [
                'id' => 0,
                'nom' => null,
                'departement' => null,
                'niveau_hierarchique' => 1,
                'ordre' => 0,
                'actif' => false,
                'parent_id' => null,
            ];
        }

        return [
            'id' => $poste->id,
            'nom' => $poste->nom,
            'departement' => $poste->departement,
            'niveau_hierarchique' => $poste->niveau_hierarchique,
            'ordre' => $poste->ordre,
            'actif' => $poste->actif,
            'parent_id' => $poste->parent_id,
        ];
    }

    public function readAll(): Collection
    {
        return PosteAdministratif::where('actif', true)
            ->get()
            ->map(fn ($p) => new PosteReadDTO(
                id: $p->id,
                nom: $p->nom,
                departement: $p->departement,
                niveau_hierarchique: $p->niveau_hierarchique,
                ordre: $p->ordre,
                actif: $p->actif,
                parent_id: $p->parent_id,
                enfants: null,
                employes_count: $p->employes->count(),
            ));
    }

    public function readByDepartement(?string $departement): Collection
    {
        return PosteAdministratif::when($departement, fn ($q) => $q->where('departement', $departement))
            ->where('actif', true)
            ->get()
            ->map(fn ($p) => new PosteReadDTO(
                id: $p->id,
                nom: $p->nom,
                departement: $p->departement,
                niveau_hierarchique: $p->niveau_hierarchique,
                ordre: $p->ordre,
                actif: $p->actif,
                parent_id: $p->parent_id,
                enfants: null,
                employes_count: $p->employes->count(),
            ));
    }

    public function organigramme(): Collection
    {
        $hydrate = function ($p, bool $avecOccupants = true) use (&$hydrate): PosteReadDTO {
            return new PosteReadDTO(
                id: $p->id,
                nom: $p->nom,
                departement: $p->departement,
                niveau_hierarchique: (int) $p->niveau_hierarchique,
                ordre: (int) $p->ordre,
                actif: (bool) $p->actif,
                parent_id: $p->parent_id === null ? null : (int) $p->parent_id,
                enfants: null,
                employes_count: $avecOccupants ? $p->employes->count() : 0,
                description: $p->description,
                parent: $p->parent === null ? null : $hydrate($p->parent, false),
                employes: $avecOccupants
                    ? $p->employes
                        ->map(fn ($e): PosteOccupantReadDTO => new PosteOccupantReadDTO(
                            nom: (string) $e->nom,
                            prenom: (string) $e->prenom,
                            nom_complet: $e->nom_complet,
                            matricule: $e->matricule,
                            email: $e->email,
                        ))
                        ->values()
                    : null,
            );
        };

        return PosteAdministratif::with(['parent', 'employes' => fn ($q) => $q->where('statut', 'actif')])
            ->where('actif', true)
            ->orderBy('niveau_hierarchique')
            ->orderBy('ordre')
            ->orderBy('nom')
            ->get()
            ->map(fn ($p): PosteReadDTO => $hydrate($p))
            ->values();
    }
}
