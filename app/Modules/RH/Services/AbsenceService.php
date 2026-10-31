<?php

declare(strict_types=1);

namespace App\Modules\RH\Services;

use App\Models\User;
use App\Modules\RH\Contracts\AbsenceServiceContract;
use App\Modules\RH\Models\AbsencePersonnel;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AbsenceService implements AbsenceServiceContract
{
    public function getAbsencesNonJustifiees(int $personnelId, Carbon $debut, Carbon $fin): Collection
    {
        return AbsencePersonnel::query()->where('employe_id', $personnelId)->where('type', 'absence_non_justifiee')->where('statut', 'validee')->whereDate('date_debut', '<=', $fin)->whereDate('date_fin', '>=', $debut)->get();
    }

    public function getJoursAbsenceNonJustifiee(int $personnelId, int $mois, int $annee): int
    {
        $debut = Carbon::create($annee, $mois, 1)->startOfMonth();

        return $this->getJoursAbsenceNonJustifieePourPeriode($personnelId, $debut, $debut->copy()->endOfMonth());
    }

    public function getJoursAbsenceNonJustifieePourPeriode(int $personnelId, Carbon $debut, Carbon $fin): int
    {
        $debut = $debut->copy()->startOfDay();
        $fin = $fin->copy()->startOfDay();
        $jours = [];

        foreach ($this->getAbsencesNonJustifiees($personnelId, $debut, $fin) as $absence) {
            $debutAbsence = $absence->date_debut->copy()->startOfDay()->max($debut);
            $finAbsence = $absence->date_fin->copy()->startOfDay()->min($fin);

            for ($jour = $debutAbsence->copy(); $jour->lte($finAbsence); $jour->addDay()) {
                if (! $jour->isWeekend()) {
                    // Indexé par date : deux absences qui se chevauchent ne comptent qu'une fois le même jour.
                    $jours[$jour->toDateString()] = true;
                }
            }
        }

        return count($jours);
    }

    public function getJoursSuspension(int $personnelId, int $mois, int $annee): int
    {
        return 0;
    }

    public function compterAbsencesValideesPourDate(Carbon $date): int
    {
        return AbsencePersonnel::query()
            ->where('statut', 'validee')
            ->whereDate('date_debut', '<=', $date)
            ->whereDate('date_fin', '>=', $date)
            ->distinct('employe_id')
            ->count('employe_id');
    }

    public function enregistrerAbsence(
        int $personnelId,
        \DateTimeInterface $debut,
        \DateTimeInterface $fin,
        string $type,
        string $motif,
        ?int $documentId = null,
        string $statut = 'en_attente',
        ?int $createdBy = null
    ): object {
        $dDebut = Carbon::instance($debut)->startOfDay();
        $dFin = Carbon::instance($fin)->startOfDay();

        if ($dFin->lt($dDebut)) {
            throw new \InvalidArgumentException("La date de fin d'absence doit être postérieure ou égale à la date de début.");
        }

        $userId = $createdBy ?? auth()->id() ?? User::query()->value('id');
        if (! $userId) {
            $userId = User::create([
                'name' => 'Système RH',
                'email' => 'systeme.rh@ambassadors.test',
                'password' => bcrypt('password'),
            ])->id;
        }

        return AbsencePersonnel::create([
            'employe_id' => $personnelId,
            'date_debut' => $dDebut->toDateString(),
            'date_fin' => $dFin->toDateString(),
            'type' => $type,
            'motif' => $motif,
            'piece_justificative_document_id' => $documentId,
            'statut' => $statut,
            'created_by' => $userId,
        ]);
    }
}
