<?php

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\Progression;
use App\Modules\Pedagogie\Models\Seance;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\CalendrierPedagogiqueReadContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Carbon\CarbonImmutable;

final class ProgressionMonitoringService
{
    private array $cache = [];

    public function mesure(Programme $p): array
    {
        if (isset($this->cache['p'.$p->id])) {
            return $this->cache['p'.$p->id];
        }
        $calcul = app(ProgressionCalculator::class)->pourChapitres($p->chapitres, (int) $p->classe_id, (int) $p->annee_scolaire_id);
        $temps = $this->temps((int) $p->annee_scolaire_id, (int) $p->niveau_id);
        $ecart = $temps === null ? null : round($calcul['pourcentage'] - $temps, 2);
        $seuil = (float) app(ParametrageServiceContract::class)->getParametre('pedagogie.progression.seuil_retard_points', 10);
        $missing = Seance::where('enseignant_id', $p->enseignant_id)->where('statut', 'dispensee')->where('progression_renseignee', false)
            ->whereHas('emploiDuTemps', fn ($q) => $q->where('annee_scolaire_id', $p->annee_scolaire_id)->where('classe_id', $p->classe_id)->where('matiere_id', $p->matiere_id))->count();
        $last = Progression::whereIn('chapitre_id', $p->chapitres->pluck('id'))->whereHas('seance', fn ($q) => $q->whereHas('emploiDuTemps', fn ($q) => $q->where('classe_id', $p->classe_id)->where('annee_scolaire_id', $p->annee_scolaire_id)))->latest('updated_at')->first();

        return $this->cache['p'.$p->id] = $calcul + ['temps' => $temps, 'ecart' => $ecart, 'derniere_maj' => $last?->updated_at?->format('d/m/Y H:i'), 'alertes' => $missing.' séance(s) à renseigner'.($ecart !== null && $ecart < -abs($seuil) ? ' • Écart à examiner' : '')];
    }

    public function details(Programme $p): array
    {
        return Progression::with('seance')->whereIn('chapitre_id', $p->chapitres->pluck('id'))
            ->whereHas('seance', fn ($q) => $q->whereHas('emploiDuTemps', fn ($q) => $q->where('classe_id', $p->classe_id)->where('annee_scolaire_id', $p->annee_scolaire_id)))
            ->orderBy('created_at')->get()->groupBy('chapitre_id')->all();
    }

    public function temps(int $anneeId, int $niveauId): ?float
    {
        $key = 't'.$anneeId.':'.$niveauId;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $annees = app(AnneeScolaireServiceContract::class);
        $params = app(ParametrageServiceContract::class);
        $year = $annees->getAnneeScolaire($anneeId);
        $days = $params->getConfigEtablissement()->jours_ouvrables;
        if (! is_array($days) || $days === []) {
            return $this->cache[$key] = null;
        }
        $periodes = collect($annees->getPeriodes($anneeId))->filter(fn ($p) => data_get($p, 'date_debut') && data_get($p, 'date_fin'));
        // Use leaf teaching periods to avoid counting gaps hidden by a parent trimester.
        $parents = $periodes->pluck('parent_id')->filter()->all();
        $periodes = $periodes->reject(fn ($p) => in_array(data_get($p, 'id'), $parents));
        if ($periodes->isEmpty()) {
            return $this->cache[$key] = null;
        }
        $start = CarbonImmutable::parse($year->date_debut)->startOfDay();
        $end = CarbonImmutable::parse($year->date_fin)->startOfDay();
        if ($end->diffInDays($start, true) > 800) {
            return $this->cache[$key] = null;
        }
        $exclusions = app(CalendrierPedagogiqueReadContract::class)->exclusions($niveauId);
        $total = 0;
        $elapsed = 0;
        $today = CarbonImmutable::today();
        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            if (! in_array($d->isoWeekday(), array_map('intval', $days), true)) {
                continue;
            }
            $excluded = false;
            foreach ($exclusions as $x) {
                if (! $x['debut'] || ! $x['fin']) {
                    continue;
                }
                if ($x['recurrent']) {
                    $md = $d->format('m-d');
                    $a = substr($x['debut'], 5);
                    $b = substr($x['fin'], 5);
                    $excluded = $a <= $b ? ($md >= $a && $md <= $b) : ($md >= $a || $md <= $b);
                } else {
                    $excluded = $d->format('Y-m-d') >= $x['debut'] && $d->format('Y-m-d') <= $x['fin'];
                }
                if ($excluded) {
                    break;
                }
            }
            if ($excluded) {
                continue;
            }
            if (! $periodes->contains(fn ($p) => $d->betweenIncluded(CarbonImmutable::parse(data_get($p, 'date_debut'))->startOfDay(), CarbonImmutable::parse(data_get($p, 'date_fin'))->endOfDay()))) {
                continue;
            }
            $total++;
            if ($d->lt($today)) {
                $elapsed++;
            }
        }

        return $this->cache[$key] = $total ? round(100 * $elapsed / $total,2) : null;
    }
}
