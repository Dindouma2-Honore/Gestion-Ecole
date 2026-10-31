<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnomalieAssiduite extends Model
{
    protected $table = 'anomalies_assiduite';

    protected $guarded = [];

    protected $casts = ['date_anomalie' => 'date', 'resolue_le' => 'datetime'];

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
