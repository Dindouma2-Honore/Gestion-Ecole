<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Exceptions;

use Exception;

class CategorieMaterielInactiveException extends Exception
{
    public function __construct(int $categorieId)
    {
        parent::__construct("La catégorie de matériel #{$categorieId} est inactive : elle ne peut plus être affectée à un nouvel équipement.");
    }
}
