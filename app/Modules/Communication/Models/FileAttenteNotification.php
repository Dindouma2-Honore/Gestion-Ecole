<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileAttenteNotification extends Model
{
    protected $table = 'file_attente_notifications';

    protected $fillable = [
        'notification_id',
        'priorite',
        'prochaine_tentative',
    ];

    protected $casts = [
        'priorite' => 'integer',
        'prochaine_tentative' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(NotificationEnvoyee::class, 'notification_id');
    }
}
