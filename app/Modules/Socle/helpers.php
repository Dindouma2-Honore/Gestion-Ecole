<?php

declare(strict_types=1);
use Spatie\Activitylog\ActivityLogger;

if (! function_exists('auditLog')) {
    function auditLog(): ActivityLogger
    {
        return activity()->withProperties(['ip_address' => request()?->ip()]);
    }
}
