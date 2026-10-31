<?php

declare(strict_types=1);

namespace App\Modules\Socle\Enums;

enum AuthorizationSubjectType: string
{
    case USER = 'user';
    case GROUP = 'group';
    case RH_POSITION = 'rh_position';
}
