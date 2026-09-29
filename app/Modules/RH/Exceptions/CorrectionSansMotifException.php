<?php

namespace App\Modules\RH\Exceptions;

use Exception;

class CorrectionSansMotifException extends Exception
{
    public function __construct()
    {
        parent::__construct("Un motif est obligatoire pour toute correction manuelle de pointage.");
    }
}
