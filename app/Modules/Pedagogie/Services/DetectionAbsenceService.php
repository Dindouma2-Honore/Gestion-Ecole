<?php

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Contracts\DetectionAbsenceServiceInterface;
use App\Modules\Pedagogie\Contracts\PresenceServiceInterface;
use App\Modules\Pedagogie\Models\AnomalieAppel;
use App\Modules\Pedagogie\Models\Seance;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Illuminate\Support\Collection;

class DetectionAbsenceService implements DetectionAbsenceServiceInterface
{
    public function __construct(
        private readonly PresenceServiceInterface $presence,
        private readonly ParametrageServiceContract $parametrage,
        // TODO: décommenter une fois le vrai contrat de notification identifié
        // (CourrierServiceContract gère le courrier administratif, pas les
        // notifications WhatsApp/SMS — ce n'est pas le bon contrat)
        // private readonly NotificationServiceContract $notification,
    ) {}

    public function detecterAnomalies(): Collection
    {
        $delaiTolerance = $this->parametrage->getConfigEtablissement()->delai_tolerance_appel_minutes ?? 15;

        $seancesConcernees = Seance::where('statut', 'programmee')
            ->whereDate('date_seance', now()->format('Y-m-d'))   // <-- whereDate() au lieu de where()
            ->whereHas('emploiDuTemps.creneau', function ($q) use ($delaiTolerance) {
                $q->where('heure_debut', '<=', now()->subMinutes($delaiTolerance)->format('H:i:s'));
            })
            ->get();

        $anomaliesCreees = collect();

        foreach ($seancesConcernees as $seance) {
            if ($this->presence->appelDejaFait($seance->id)) {
                continue;
            }

            $anomalie = AnomalieAppel::firstOrCreate(
                ['seance_id' => $seance->id],
                ['detectee_le' => now()]
            );

            // TODO: notification désactivée en attendant le vrai contrat —
            // décommenter et corriger une fois confirmé.

            $anomaliesCreees->push($anomalie);
        }

        return $anomaliesCreees;
    }

    public function marquerResolue(int $anomalieId): void
    {
        AnomalieAppel::where('id', $anomalieId)->update(['resolue' => true]);
    }

    public function getAnomaliesNonResolues(): Collection
    {
        return AnomalieAppel::where('resolue', false)->with('seance')->get();
    }
}
