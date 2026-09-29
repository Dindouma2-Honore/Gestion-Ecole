<?php

declare(strict_types=1);

namespace App\Modules\Socle\Exceptions;

use Exception;

class FormatNumerotationInexistantException extends Exception
{
    public function __construct(string $typeDocument)
    {
        parent::__construct("Aucun format de numérotation configuré pour le type de document '{$typeDocument}'. Contactez l'administrateur pour le configurer dans Paramétrage général.");
    }
}
