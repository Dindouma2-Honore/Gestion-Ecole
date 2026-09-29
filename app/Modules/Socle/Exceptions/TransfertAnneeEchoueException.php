<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use Exception;

class TransfertAnneeEchoueException extends Exception
{
    public function __construct(string $raison)
    {
        parent::__construct("Le transfert des élèves vers la nouvelle année a échoué : {$raison}");
    }
}
