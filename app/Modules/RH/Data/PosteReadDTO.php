<?php

declare(strict_types=1);

namespace App\Modules\RH\Data;

use App\Modules\RH\Models\PosteAdministratif;
use Illuminate\Support\Collection;

readonly class PosteReadDTO
{
    public function __construct(
        public int $id,
        public ?string $nom,
        public ?string $departement,
        public int $niveau_hierarchique,
        public int $ordre,
        public bool $actif,
        public ?int $parent_id,
        public ?Collection $enfants,
        public ?int $employes_count,
        public ?string $description = null,
        public ?PosteReadDTO $parent = null,
        public ?Collection $employes = null,
    ) {}

    public static function fromModel(PosteAdministratif $poste): self
    {
        return new self(
            id: $poste->id,
            nom: $poste->nom,
            departement: $poste->departement,
            niveau_hierarchique: $poste->niveau_hierarchique,
            ordre: $poste->ordre,
            actif: $poste->actif,
            parent_id: $poste->parent_id,
            enfants: $poste->children->isNotEmpty() ? $poste->children->map(fn ($c) => new self(
                id: $c->id,
                nom: $c->nom,
                departement: $c->departement,
                niveau_hierarchique: $c->niveau_hierarchique,
                ordre: $c->ordre,
                actif: $c->actif,
                parent_id: $c->parent_id,
                enfants: null,
                employes_count: null,
            ))->collect() : null,
            employes_count: $poste->employes->count(),
        );
    }

    public function read(int $posteId): ?array
    {
        $poste = PosteAdministratif::find($posteId);
        if (! $poste) {
            return null;
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
            ->map(fn ($p) => [
                'id' => $p->id,
                'nom' => $p->nom,
                'departement' => $p->departement,
                'niveau_hierarchique' => $p->niveau_hierarchique,
                'ordre' => $p->ordre,
                'actif' => $p->actif,
                'parent_id' => $p->parent_id,
            ]);
    }

    public function readByDepartement(?string $departement): Collection
    {
        return PosteAdministratif::when($departement, fn ($q) => $q->where('departement', $departement))
            ->where('actif', true)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'nom' => $p->nom,
                'departement' => $p->departement,
                'niveau_hierarchique' => $p->niveau_hierarchique,
                'ordre' => $p->ordre,
                'actif' => $p->actif,
                'parent_id' => $p->parent_id,
            ]);
    }
}

readonly class PosteOccupantReadDTO
{
    public function __construct(
        public string $nom,
        public string $prenom,
        public string $nom_complet,
        public ?string $matricule,
        public ?string $email,
    ) {}
}
