<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Services;

use App\Modules\Scolarite\Contracts\ParentTuteurServiceInterface;
use App\Modules\Scolarite\Exceptions\EleveIntrouvableException;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\ParentTuteur;

class ParentTuteurService implements ParentTuteurServiceInterface
{
    public function existe(int $parentId): bool
    {
        return ParentTuteur::whereKey($parentId)->exists();
    }

    public function getParent(int $parentId): array
    {
        $parent = ParentTuteur::findOrFail($parentId);

        return [
            'id' => $parent->id,
            'nom' => $parent->nom,
            'prenom' => $parent->prenom,
            'telephone' => $parent->telephone,
        ];
    }

    public function creer(array $donnees): array
    {
        $parent = ParentTuteur::create([
            'nom' => $donnees['nom'],
            'prenom' => $donnees['prenom'],
            'profession' => $donnees['profession'] ?? null,
            'telephone' => $donnees['telephone'] ?? null,
            'email' => $donnees['email'] ?? null,
        ]);

        return $parent->toArray();
    }

    public function lierAEleve(int $parentId, int $eleveId, array $responsabilites): void
    {
        $eleve = Eleve::find($eleveId);

        if (! $eleve) {
            throw EleveIntrouvableException::pourId($eleveId);
        }

        $eleve->parentsTuteurs()->syncWithoutDetaching([
            $parentId => [
                'lien' => $responsabilites['lien'] ?? null,
                'responsable_legal' => $responsabilites['responsable_legal'] ?? false,
                'responsable_paiement' => $responsabilites['responsable_paiement'] ?? false,
                'autorise_recuperation' => $responsabilites['autorise_recuperation'] ?? false,
            ],
        ]);
    }
    public function getPersonnesAutoriseesRecuperer(int $eleveId): array
{
    $eleve = Eleve::find($eleveId);

    if (! $eleve) {
        throw EleveIntrouvableException::pourId($eleveId);
    }

    return $eleve->parentsTuteurs()
        ->wherePivot('autorise_recuperation', true)
        ->get()
        ->map(fn (ParentTuteur $p) => [
            'id' => $p->id,
            'nom' => $p->nom,
            'prenom' => $p->prenom,
            'telephone' => $p->telephone,
        ])
        ->all();
}
}
