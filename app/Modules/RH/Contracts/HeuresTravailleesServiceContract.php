<?php

declare(strict_types=1);

namespace App\Modules\RH\Contracts;

interface HeuresTravailleesServiceContract
{
    public function getNombreHeuresTravaillees(int $employeId, int $mois, int $annee): float;

    /** Heures pointées (arrivée et départ renseignés) sur une période arbitraire, bornes incluses. */
    public function getNombreHeuresTravailleesPourPeriode(int $employeId, \DateTimeInterface $periodeDebut, \DateTimeInterface $periodeFin): float;
}
