<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Exceptions;

use Exception;

class CategorieFraisEnUsageException extends Exception
{
    public function __construct(int $categorieId)
    {
        parent::__construct(
            "La catégorie de frais #{$categorieId} ne peut pas être supprimée : des frais y sont encore rattachés."
        );
    }
}
