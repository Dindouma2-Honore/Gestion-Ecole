<?php

declare(strict_types=1);

namespace App\Modules\Finances\Exceptions;

use Exception;

class RemiseSansMotifException extends Exception
{
    public function __construct()
    {
        parent::__construct('Un motif est obligatoire pour accorder une remise ou une exonération.');
    }
}
