<?php

declare(strict_types=1);

namespace App\Modules\RH\Services;

use App\Modules\RH\Contracts\HeuresTravailleesServiceContract;
use App\Modules\RH\Models\Pointage;
use Carbon\Carbon;

class HeuresTravailleesServiceStub implements HeuresTravailleesServiceContract
{
    public function getNombreHeuresTravaillees(int $employeId, int $mois, int $annee): float
    {
        return $this->getNombreHeuresTravailleesPourPeriode(
            $employeId,
            Carbon::create($annee, $mois, 1),
            Carbon::create($annee, $mois, 1)->endOfMonth(),
        );
    }

    public function getNombreHeuresTravailleesPourPeriode(int $employeId, \DateTimeInterface $periodeDebut, \DateTimeInterface $periodeFin): float
    {
        $debut = Carbon::instance($periodeDebut)->startOfDay();
        $fin = Carbon::instance($periodeFin)->endOfDay();

        return (float) Pointage::query()
            ->where('employe_id', $employeId)
            ->whereBetween('date_pointage', [$debut->toDateString(), $fin->toDateString()])
            ->whereNotNull('heure_arrivee')
            ->whereNotNull('heure_depart')
            ->get()
            ->sum(function (Pointage $pointage): float {
                $date = $pointage->date_pointage->toDateString();
                $arrivee = Carbon::parse($date.' '.$pointage->heure_arrivee);
                $depart = Carbon::parse($date.' '.$pointage->heure_depart);

                if ($depart->lessThan($arrivee)) {
                    $depart->addDay();
                }

                return round($arrivee->diffInMinutes($depart) / 60, 2);
            });
    }
}
