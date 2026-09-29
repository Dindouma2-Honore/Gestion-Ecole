<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Spatie\Activitylog\Models\Activity;

class ActivityLog extends Activity
{
    protected static function booted(): void
    {
        static::creating(function (self $activity): void {
            $activity->ip_address ??= $activity->getExtraProperty('ip_address') ?? request()?->ip();
            $activity->motif ??= $activity->getExtraProperty('motif');
        });
    }
}
