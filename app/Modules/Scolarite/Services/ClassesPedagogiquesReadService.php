<?php

namespace App\Modules\Scolarite\Services;

use App\Modules\Scolarite\Contracts\ClassesPedagogiquesReadContract;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Socle\Contracts\Scopes\NiveauScope;

final class ClassesPedagogiquesReadService implements ClassesPedagogiquesReadContract
{
    public function pourIds(array $ids, int $anneeId): array
    {
        return Classe::withoutGlobalScope(NiveauScope::class)
            ->whereIn('id', $ids)->where('annee_scolaire_id', $anneeId)
            ->get(['id', 'nom', 'niveau_id', 'filiere_id', 'statut', 'annee_scolaire_id'])->keyBy('id')->toArray();
    }

    public function pourAnnee(int $anneeId): array
    {
        return Classe::where('annee_scolaire_id', $anneeId)->orderBy('nom')
            ->get(['id', 'nom', 'niveau_id', 'filiere_id', 'statut', 'annee_scolaire_id'])->keyBy('id')->toArray();
    }
}
