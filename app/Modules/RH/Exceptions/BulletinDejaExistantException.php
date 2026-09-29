<?php

namespace App\Modules\RH\Exceptions;

use Exception;

class BulletinDejaExistantException extends Exception
{
    public function __construct(int $employeId, int $mois, int $annee)
    {
        parent::__construct("Un bulletin de paie existe déjà pour l'employé #{$employeId} pour {$mois}/{$annee}. Supprimez-le d'abord si vous voulez le recalculer.");
    }
}
