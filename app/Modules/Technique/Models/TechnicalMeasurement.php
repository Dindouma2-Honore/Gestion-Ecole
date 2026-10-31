<?php

declare(strict_types=1);

namespace App\Modules\Technique\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicalMeasurement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['measured_at' => 'immutable_datetime', 'metrics' => 'array'];
    }

    public function effectiveStatus(): string
    {
        return ! $this->measured_at || $this->measured_at->lt(now()->subSeconds(config('technique.freshness_seconds', 180)))
            ? 'UNKNOWN' : $this->status;
    }
}
