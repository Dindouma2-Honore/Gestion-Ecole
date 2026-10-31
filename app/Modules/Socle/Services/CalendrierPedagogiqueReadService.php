<?php

namespace App\Modules\Socle\Services;

use App\Modules\Socle\Contracts\CalendrierPedagogiqueReadContract;
use App\Modules\Socle\Models\JourFerie;

final class CalendrierPedagogiqueReadService implements CalendrierPedagogiqueReadContract
{
    public function exclusions(int $niveauId): array
    {
        return JourFerie::where(fn ($q) => $q->whereNull('niveau_id')->orWhere('niveau_id', $niveauId))
            ->where(fn ($q) => $q->whereNull('statut')->orWhereNotIn('statut', ['annule', 'annulee', 'inactif']))
            ->where(fn ($q) => $q->where('affecte_cours', true)->orWhere('etablissement_ferme', true))
            ->get()->map(fn ($j) => ['debut' => ($j->date_debut ?? $j->date)?->format('Y-m-d'), 'fin' => ($j->date_fin ?? $j->date_debut ?? $j->date)?->format('Y-m-d'), 'recurrent' => $j->recurrent])->all();
    }
}
