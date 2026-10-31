<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Enums;

enum StatutProgressionElementEnum: string
{
    case NON_COMMENCEE = 'non_commencee';
    case COMMENCEE = 'commencee';
    case POURSUIVIE = 'poursuivie';
    case TERMINEE = 'terminee';

    public function label(): string
    {
        return match ($this) {
            self::NON_COMMENCEE => 'Non commencée',
            self::COMMENCEE => 'Commencée',
            self::POURSUIVIE => 'Poursuivie',
            self::TERMINEE => 'Terminée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NON_COMMENCEE => 'gray',
            self::COMMENCEE => 'info',
            self::POURSUIVIE => 'warning',
            self::TERMINEE => 'success',
        };
    }
}
