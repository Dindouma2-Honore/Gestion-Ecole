<?php

declare(strict_types=1);

namespace App\Modules\Socle\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait AuditsSensitiveChanges
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        $options = LogOptions::defaults()
            ->useLogName('donnees_sensibles')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event): string => class_basename($this).' '.$event);

        return $this->getFillable() === [] ? $options->logAll() : $options->logOnly($this->getFillable());
    }
}
