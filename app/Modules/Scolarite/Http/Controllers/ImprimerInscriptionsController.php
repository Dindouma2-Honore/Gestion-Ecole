<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Http\Controllers;

use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImprimerInscriptionsController
{
    public function __invoke(Request $request): View
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $colonnes = collect(explode(',', (string) $request->query('colonnes', '')))
            ->filter()
            ->values()
            ->all();

        if ($colonnes === []) {
            $colonnes = ['matricule', 'eleve', 'classe', 'annee', 'statut'];
        }

        $inscriptions = Inscription::query()
            ->with(['eleve', 'classe'])
            ->whereIn('id', $ids)
            ->orderBy('classe_id')
            ->orderBy('id')
            ->get();

        $total = $inscriptions->count();
        $filles = $inscriptions->filter(fn (Inscription $i): bool => $i->eleve?->sexe === 'F')->count();
        $garcons = $inscriptions->filter(fn (Inscription $i): bool => $i->eleve?->sexe === 'M')->count();

        try {
            $anneeScolaire = app(AnneeScolaireServiceContract::class)->getAnneeCourante()->libelle;
        } catch (\Throwable) {
            $anneeScolaire = null;
        }

        return view('scolarite::filament.impression', compact(
            'inscriptions', 'colonnes', 'total', 'filles', 'garcons', 'anneeScolaire',
        ));
    }
}
