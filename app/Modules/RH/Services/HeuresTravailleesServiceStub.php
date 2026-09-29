<?php

declare(strict_types=1);

namespace App\Modules\RH\Services;

use App\Modules\RH\Contracts\HeuresTravailleesServiceContract;

// STUB — à remplacer quand le module propriétaire des heures travaillées sera implémenté.
class HeuresTravailleesServiceStub implements HeuresTravailleesServiceContract
{
    public function getNombreHeuresTravaillees(int $employeId, int $mois, int $annee): float
    {
        return 0.0;
    }
}
