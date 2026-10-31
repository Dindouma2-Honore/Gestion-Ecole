<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Exceptions;

use Exception;

class CategorieMaterielInconnueException extends Exception
{
    public function __construct(int $categorieId)
    {
        parent::__construct("La catégorie de matériel #{$categorieId} est introuvable.");
    }
}
