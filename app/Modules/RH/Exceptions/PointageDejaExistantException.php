<?php

namespace App\Modules\RH\Exceptions;

use Exception;

class PointageDejaExistantException extends Exception
{
    public function __construct(int $employeId, string $date)
    {
        parent::__construct("Un pointage existe déjà pour l'employé #{$employeId} à la date {$date}.");
    }
}
