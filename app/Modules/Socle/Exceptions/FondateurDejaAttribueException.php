<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use RuntimeException;

class FondateurDejaAttribueException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Un Fondateur actif existe déjà. Utilisez le transfert explicite du rôle Fondateur.');
    }
}
