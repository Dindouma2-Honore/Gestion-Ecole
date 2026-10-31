<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Exceptions;

use Exception;

class CategorieMaterielUtiliseeException extends Exception
{
    public function __construct(int $categorieId, int $nbEquipements)
    {
        parent::__construct("La catégorie de matériel #{$categorieId} est encore utilisée par {$nbEquipements} équipement(s) : suppression refusée.");
    }
}
