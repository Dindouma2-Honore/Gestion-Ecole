<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageDestinataire extends Model
{
    protected $table = 'message_destinataires';

    protected $fillable = [
        'message_id',
        'parent_id',
        'lu',
        'notification_id',
    ];

    protected $casts = [
        'lu' => 'boolean',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(MessageParent::class, 'message_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(NotificationEnvoyee::class, 'parent_id');
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(NotificationEnvoyee::class, 'notification_id');
    }
}
