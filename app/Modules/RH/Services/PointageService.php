<?php

namespace App\Modules\RH\Services;

use App\Modules\RH\Contracts\CongeServiceContract;
use App\Modules\RH\Contracts\PointageServiceContract;
use App\Modules\RH\Exceptions\CorrectionSansMotifException;
use App\Modules\RH\Models\Pointage;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class PointageService implements PointageServiceContract
{
    public function __construct(
        private readonly CongeServiceContract $conge,
        private readonly ParametrageServiceContract $parametrage,
        private readonly AuditServiceContract $audit
    ) {}

    public function enregistrerPointage(int $employeId, \DateTimeInterface $dateHeure, string $type, string $modePointage, ?string $terminalId = null): void
    {
        $date = $dateHeure->format('Y-m-d');
        $heure = $dateHeure->format('H:i:s');

        $pointage = Pointage::firstOrCreate(
            ['employe_id' => $employeId, 'date_pointage' => $date],
            ['mode_pointage' => $modePointage, 'terminal_id' => $terminalId]
        );

        if ($type === 'arrivee' && is_null($pointage->heure_arrivee)) {
            $pointage->update(['heure_arrivee' => $heure]);
        } elseif ($type === 'depart') {
            $pointage->update(['heure_depart' => $heure]);
        }
    }

    public function corrigerManuel(int $pointageId, ?string $heureArrivee, ?string $heureDepart, string $motif): void
    {
        if (empty(trim($motif))) {
            throw new CorrectionSansMotifException();
        }

        $pointage = Pointage::findOrFail($pointageId);

        $pointage->update([
            'heure_arrivee' => $heureArrivee ?? $pointage->heure_arrivee,
            'heure_depart' => $heureDepart ?? $pointage->heure_depart,
            'correction_manuelle' => true,
            'corrige_par' => Auth::id(),
            'motif_correction' => $motif,
        ]);

        $this->audit->enregistrerAvecMotif($pointage, "Correction manuelle du pointage #{$pointageId}", $motif);
    }

    public function getJoursAbsenceNonJustifiee(int $employeId, int $mois, int $annee): int
    {
        $joursOuvrables = $this->getJoursOuvrablesDuMois($mois, $annee);
        $count = 0;

        foreach ($joursOuvrables as $jour) {
            $aPointe = Pointage::where('employe_id', $employeId)
                ->where('date_pointage', $jour->format('Y-m-d'))
                ->whereNotNull('heure_arrivee')
                ->exists();

            if ($aPointe) {
                continue;
            }

            if ($this->conge->estEnCongeAutorise($employeId, $jour)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    private function getJoursOuvrablesDuMois(int $mois, int $annee): Collection
    {
        $periode = CarbonPeriod::create(
            Carbon::create($annee, $mois, 1),
            Carbon::create($annee, $mois, 1)->endOfMonth()
        );

        return collect($periode)->filter(function ($jour) {
            if ($jour->isWeekend()) {
                return false;
            }
            if ($this->parametrage->estJourFerie($jour)) {
                return false;
            }
            return true;
        });
    }

    public function genererRapportMensuel(int $employeId, int $mois, int $annee): object
    {
        $joursOuvrables = $this->getJoursOuvrablesDuMois($mois, $annee);
        $absencesNonJustifiees = $this->getJoursAbsenceNonJustifiee($employeId, $mois, $annee);

        $pointages = Pointage::where('employe_id', $employeId)
            ->whereYear('date_pointage', $annee)
            ->whereMonth('date_pointage', $mois)
            ->get();

        return (object) [
            'employe_id' => $employeId,
            'mois' => $mois,
            'annee' => $annee,
            'total_jours_ouvrables' => $joursOuvrables->count(),
            'jours_presents' => $pointages->whereNotNull('heure_arrivee')->count(),
            'absences_non_justifiees' => $absencesNonJustifiees,
        ];
    }
}
