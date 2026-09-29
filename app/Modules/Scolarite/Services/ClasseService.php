<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Services;

use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Scolarite\Exceptions\ClasseIntrouvableException;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Inscription;

class ClasseService implements ClasseServiceInterface
{
    public function existe(int $classeId): bool
    {
        return Classe::whereKey($classeId)->exists();
    }

    public function getNomClasse(int $classeId): string
    {
        $classe = Classe::find($classeId);

        if (! $classe) {
            throw ClasseIntrouvableException::pourId($classeId);
        }

        return $classe->nom;
    }

    public function getNiveauId(int $classeId): int
    {
        $classe = Classe::find($classeId);

        if (! $classe) {
            throw ClasseIntrouvableException::pourId($classeId);
        }

        return $classe->niveau_id;
    }

    public function getEffectif(int $classeId): int
    {
        return Inscription::where('classe_id', $classeId)
            ->whereIn('statut', ['en_attente_versement', 'active', 'validee'])
            ->count();
    }

    public function getPlacesRestantes(int $classeId): int
    {
        $classe = Classe::find($classeId);

        if (! $classe) {
            throw ClasseIntrouvableException::pourId($classeId);
        }

        return max(0, $classe->capacite_max - $this->getEffectif($classeId));
    }

    public function getToutesLesClasses(int $anneeScolaireId): array
    {
        return Classe::where('annee_scolaire_id', $anneeScolaireId)
            ->orderBy('nom')
            ->get()
            ->map(fn (Classe $c) => ['id' => $c->id, 'nom' => $c->nom])
            ->all();
    }
}
