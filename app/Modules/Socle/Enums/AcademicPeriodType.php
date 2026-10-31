<?php

declare(strict_types=1);

namespace App\Modules\Socle\Enums;

enum AcademicPeriodType: string
{
    case SEMESTER = 'semestre';
    case TRIMESTER = 'trimestre';
    case SEQUENCE = 'sequence';
}
