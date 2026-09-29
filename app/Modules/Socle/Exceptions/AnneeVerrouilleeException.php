<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use Exception;

class AnneeVerrouilleeException extends Exception
{
    public function __construct(string $libelleAnnee, string $statut)
    {
        parent::__construct("L'année scolaire {$libelleAnnee} est {$statut} — toute modification est bloquée. Seul le Fondateur peut corriger une année clôturée (jamais une année archivée).");
    }
}
