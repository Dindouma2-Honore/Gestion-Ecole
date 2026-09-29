<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class MouvementCaisse extends Model
{
    protected $table = 'mouvements_caisse';

    protected $guarded = [];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Un mouvement de caisse ne peut jamais être modifié.');
        });

        static::deleting(function (): never {
            throw new LogicException('Un mouvement de caisse ne peut jamais être supprimé.');
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SessionCaisse::class, 'session_caisse_id');
    }
}
