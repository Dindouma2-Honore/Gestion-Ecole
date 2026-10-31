<?php

declare(strict_types=1);

namespace App\Modules\Socle\Enums;

enum AuthorizationAction: string
{
    case READ = 'read';
    case WRITE = 'write';
    case EXECUTE = 'execute';
    case PRINT = 'print';
}
