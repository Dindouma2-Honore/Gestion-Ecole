<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Exceptions;

use Exception;

class CategorieMaterielChampsInvalidesException extends Exception
{
    public function __construct(string $champ)
    {
        parent::__construct("La catégorie de matériel requiert un « {$champ} » non vide.");
    }
}
