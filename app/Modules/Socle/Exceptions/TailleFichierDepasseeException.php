<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use Exception;

class TailleFichierDepasseeException extends Exception
{
    public function __construct(int $tailleMaxKo)
    {
        parent::__construct("Le fichier dépasse la taille maximale autorisée de {$tailleMaxKo} Ko.");
    }
}
