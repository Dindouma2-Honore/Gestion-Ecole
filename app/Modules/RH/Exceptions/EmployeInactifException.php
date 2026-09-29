<?php

namespace App\Modules\RH\Exceptions;

use Exception;

class EmployeInactifException extends Exception
{
    public function __construct(int $employeId, string $statut)
    {
        parent::__construct("L'employé #{$employeId} a le statut '{$statut}' — cette opération n'est pas autorisée pour un employé inactif.");
    }
}
