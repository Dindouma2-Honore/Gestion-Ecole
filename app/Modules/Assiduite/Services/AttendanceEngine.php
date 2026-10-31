<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Services;

use App\Modules\Assiduite\Contracts\AttendanceEngineInterface;
use App\Modules\Assiduite\Models\AnomalieAssiduite;
use App\Modules\Assiduite\Models\AssiduiteHistorique;
use App\Modules\Assiduite\Models\AssiduiteJournaliere;
use App\Modules\Assiduite\Models\JustificationAssiduite;
use App\Modules\Socle\Contracts\AcademicCalendarContract;
use App\Modules\Socle\Contracts\SchoolProfileContract;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AttendanceEngine implements AttendanceEngineInterface
{
    public function __construct(
        private readonly AcademicCalendarContract $calendrier,
        private readonly SchoolProfileContract $schoolProfile,
    ) {}

    public function calculer(string $personneType, int $personneId, DateTimeInterface $date): AssiduiteJournaliere
    {
        if (! in_array($personneType, ['eleve', 'personnel'], true)) {
            throw new InvalidArgumentException('Type de personne invalide.');
        }
        $jour = Carbon::instance($date)->startOfDay();
        $resultat = $personneType === 'eleve' ? $this->calculerEleve($personneId, $jour) : $this->calculerPersonnel($personneId, $jour);
        $justifie = JustificationAssiduite::query()
            ->where('personne_type', $personneType)
            ->where('personne_id', $personneId)
            ->whereDate('date_concernee', $jour)
            ->where('decision', 'acceptee')
            ->exists();
        if ($justifie && in_array($resultat['statut'], ['absent_presume', 'absent_confirme', 'retard', 'depart_anticipe'], true)) {
            $resultat['statut'] = 'absence_justifiee';
        }

        return AssiduiteJournaliere::query()->updateOrCreate(
            ['personne_type' => $personneType, 'personne_id' => $personneId, 'date_assiduite' => $jour->toDateString()],
            [...$resultat, 'calcule_le' => now()]
        );
    }

    public function recalculer(string $personneType, int $personneId, DateTimeInterface $date): AssiduiteJournaliere
    {
        $ancien = AssiduiteJournaliere::query()->where(['personne_type' => $personneType, 'personne_id' => $personneId, 'date_assiduite' => Carbon::instance($date)->toDateString()])->first();
        $nouveau = $this->calculer($personneType, $personneId, $date);
        if ($ancien && $ancien->statut !== $nouveau->statut) {
            AssiduiteHistorique::query()->create(['assiduite_journaliere_id' => $nouveau->id, 'action' => 'recalcul', 'ancien_statut' => $ancien->statut, 'nouveau_statut' => $nouveau->statut, 'effectue_par' => auth()->id(), 'effectue_le' => now()]);
        }

        return $nouveau;
    }

    private function calculerEleve(int $eleveId, Carbon $jour): array
    {
        if (! $this->calendrier->isTeachingDay($jour)) {
            return $this->resultat('non_attendu', 'calendrier');
        }
        $presences = DB::table('presences')->join('seances', 'seances.id', '=', 'presences.seance_id')->where('presences.eleve_id', $eleveId)->whereDate('seances.date_seance', $jour)->pluck('presences.statut');
        if ($presences->isEmpty()) {
            $classeId = DB::table('inscriptions')->where('eleve_id', $eleveId)->whereIn('statut', ['active', 'validee'])->latest('id')->value('classe_id');
            $seancesPrevues = $classeId ? DB::table('seances')->join('emplois_du_temps', 'emplois_du_temps.id', '=', 'seances.emploi_du_temps_id')->where('emplois_du_temps.classe_id', $classeId)->whereDate('seances.date_seance', $jour)->count() : 0;
            if ($seancesPrevues > 0) {
                $this->anomalie('MISSING_ATTENDANCE_CALL', 'eleve', $eleveId, $jour, 'pedagogie', 'Une ou plusieurs séances prévues ne comportent aucun appel.', 'haute');
            }

            return $this->resultat($seancesPrevues > 0 ? 'a_verifier' : 'non_attendu', $seancesPrevues > 0 ? 'seance_sans_appel' : 'aucune_seance', 60, ['seances_prevues' => $seancesPrevues]);
        }
        $statut = $presences->contains('absent') ? 'absent_presume' : ($presences->contains('retard') ? 'retard' : ($presences->contains('depart_anticipe') ? 'depart_anticipe' : 'present'));

        return $this->resultat($statut, 'appel_pedagogique', 100, ['seances_renseignees' => $presences->count()]);
    }

    private function calculerPersonnel(int $employeId, Carbon $jour): array
    {
        if (! $this->calendrier->isSchoolOpen($jour)) {
            return $this->resultat('non_attendu', 'calendrier');
        }
        $conge = DB::table('conges')->where('employe_id', $employeId)->whereIn('statut', ['approuve', 'en_cours'])->whereDate('date_debut', '<=', $jour)->whereDate('date_fin', '>=', $jour)->first();
        if ($conge) {
            return $this->resultat(str_contains((string) $conge->type, 'mission') ? 'mission' : 'conge', 'rh');
        }
        $absence = DB::table('absences_personnel')->where('employe_id', $employeId)->where('statut', 'validee')->whereDate('date_debut', '<=', $jour)->whereDate('date_fin', '>=', $jour)->first();
        if ($absence) {
            return $this->resultat($absence->type === 'conge' ? 'conge' : 'absence_justifiee', 'rh');
        }
        $pointage = DB::table('pointages')->where('employe_id', $employeId)->whereDate('date_pointage', $jour)->first();
        if (! $pointage || ! $pointage->heure_arrivee) {
            return $this->resultat('absent_presume', 'pointage', 70);
        }
        $profile = $this->schoolProfile->profile();
        $attendue = $profile->openingTime ?: '07:30:00';
        $tolerance = $profile->lateToleranceMinutes;
        $arrivee = Carbon::parse($jour->toDateString().' '.$pointage->heure_arrivee);
        $heureAttendue = Carbon::parse($jour->toDateString().' '.$attendue);
        $retard = max(0, (int) $heureAttendue->diffInMinutes($arrivee, false));
        if (! $pointage->heure_depart) {
            $this->anomalie('MISSING_EXIT_PUNCH', 'personnel', $employeId, $jour, 'pointage', 'Pointage d’entrée sans pointage de sortie.', 'normale');
        }
        $statut = $retard > $tolerance ? 'retard' : 'present';

        return [...$this->resultat($statut, 'pointage', 100), 'heure_attendue' => $attendue, 'heure_arrivee' => $pointage->heure_arrivee, 'heure_depart' => $pointage->heure_depart, 'minutes_retard' => $retard, 'minutes_presence' => $pointage->heure_depart ? max(0, (int) $arrivee->diffInMinutes(Carbon::parse($jour->toDateString().' '.$pointage->heure_depart), false)) : 0];
    }

    private function resultat(string $statut, string $source, int $confiance = 100, array $details = []): array
    {
        return ['statut' => $statut, 'source' => $source, 'confiance' => $confiance, 'details' => $details, 'minutes_retard' => 0, 'minutes_depart_anticipe' => 0, 'minutes_presence' => 0];
    }

    private function anomalie(string $type, string $personneType, int $personneId, Carbon $jour, string $source, string $description, string $priorite): void
    {
        AnomalieAssiduite::query()->firstOrCreate(['type' => $type, 'personne_type' => $personneType, 'personne_id' => $personneId, 'date_anomalie' => $jour->toDateString()], ['source' => $source, 'description' => $description, 'priorite' => $priorite]);
    }
}
