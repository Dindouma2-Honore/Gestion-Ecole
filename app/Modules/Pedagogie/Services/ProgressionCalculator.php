<?php

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Models\Progression;
use Illuminate\Support\Collection;

final class ProgressionCalculator
{
    /** Only completed work counts. Use hours only when every tracked item has a positive weight. */
    public function pourChapitres(Collection $elements, int $classeId, int $anneeId): array
    {
        $parents = $elements->pluck('parent_id')->filter()->all();
        $elements = $elements->reject(fn ($e) => in_array($e->id, $parents));
        $ids = $elements->pluck('id');
        $termines = Progression::whereIn('chapitre_id', $ids)->where('chapitre_termine', true)
            ->whereHas('seance', fn ($q) => $q->where('statut', 'dispensee')
                ->whereHas('emploiDuTemps', fn ($q) => $q->where('classe_id', $classeId)->where('annee_scolaire_id', $anneeId)))
            ->pluck('chapitre_id')->unique();
        $pondere = $elements->isNotEmpty() && $elements->every(fn ($e) => (float) $e->volume_horaire_prevu > 0);
        $total = $elements->sum(fn ($e) => $pondere ? (float) $e->volume_horaire_prevu : 1);
        $realise = $elements->whereIn('id', $termines)->sum(fn ($e) => $pondere ? (float) $e->volume_horaire_prevu : 1);

        return ['pourcentage' => $total > 0 ? round(100 * $realise / $total, 2) : 0.0,
            'total' => $elements->count(), 'termines' => $termines->count(),
            'strategie' => $pondere ? 'heures' : 'elements', 'poids_prevu' => $total, 'poids_realise' => $realise];
    }
}
