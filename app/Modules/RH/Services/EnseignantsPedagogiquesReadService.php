<?php

namespace App\Modules\RH\Services;

use App\Modules\RH\Contracts\EnseignantsPedagogiquesReadContract;
use App\Modules\RH\Models\Enseignant;

final class EnseignantsPedagogiquesReadService implements EnseignantsPedagogiquesReadContract
{
    public function tous(): array
    {
        return Enseignant::with('employe')->get()->mapWithKeys(fn ($e) => [$e->id => [
            'id' => $e->id, 'nom' => trim(($e->employe?->prenom ?? '').' '.($e->employe?->nom ?? '')),
            'matricule' => $e->employe?->matricule, 'actif' => $e->employe?->statut === 'actif',
        ]])->all();
    }
}
