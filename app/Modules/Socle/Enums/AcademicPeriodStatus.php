<?php

declare(strict_types=1);

namespace App\Modules\Socle\Enums;

enum AcademicPeriodStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
}
