<?php

namespace App\Modules\RH\Exceptions;

use Exception;

class TypePrimeInexistantException extends Exception
{
    public function __construct(string $code)
    {
        parent::__construct("Le type de prime '{$code}' n'existe pas. Types disponibles : rendement, anciennete, responsabilite, exceptionnelle, fin_annee.");
    }
}
