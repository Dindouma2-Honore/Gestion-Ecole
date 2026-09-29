<?php

declare(strict_types=1);

namespace App\Modules\RH\Contracts;

interface HeuresTravailleesServiceContract
{
    public function getNombreHeuresTravaillees(int $employeId, int $mois, int $annee): float;
}
