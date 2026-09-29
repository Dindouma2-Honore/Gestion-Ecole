<?php

namespace App\Modules\RH\Exceptions;

use Exception;

class SanctionSansMotifException extends Exception
{
    public function __construct()
    {
        parent::__construct("Un motif est obligatoire pour enregistrer une sanction disciplinaire.");
    }
}
