<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Modules\Socle\Contracts\TransfertElevesAnneeContract;
use App\Modules\Socle\Exceptions\TransfertAnneeEchoueException;

class TransfertElevesIndisponible implements TransfertElevesAnneeContract
{
    public function transferer(int $anneeSourceId, int $anneeDestinationId): void
    {
        throw new TransfertAnneeEchoueException(
            'le module Scolarité ne fournit pas encore le contrat de transfert des inscriptions'
        );
    }
}
