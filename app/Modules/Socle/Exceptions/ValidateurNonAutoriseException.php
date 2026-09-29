<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use Exception;

class ValidateurNonAutoriseException extends Exception
{
    public function __construct(int $validateurId, int $tacheId)
    {
        parent::__construct("L'utilisateur #{$validateurId} n'est pas autorisé à valider l'étape courante de la tâche #{$tacheId} (ce n'est pas son tour, ou le circuit est déjà terminé).");
    }
}
