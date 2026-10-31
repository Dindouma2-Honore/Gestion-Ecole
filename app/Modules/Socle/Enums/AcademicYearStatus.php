<?php

declare(strict_types=1);

namespace App\Modules\Socle\Enums;

enum AcademicYearStatus: string
{
    case PREPARATION = 'preparation';
    case ACTIVE = 'active';
    case CLOTURE_EN_COURS = 'cloture_en_cours';
    case CLOTUREE = 'cloturee';
    case ARCHIVEE = 'archivee';
}
