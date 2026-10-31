<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Enums;

enum NatureMateriel: string
{
    case Durable = 'durable';
    case Consommable = 'consommable';

    public function label(): string
    {
        return $this === self::Durable ? 'Durable' : 'Consommable';
    }
}
