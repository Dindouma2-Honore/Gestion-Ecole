<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionCaisse extends Model
{
    protected $table = 'sessions_caisse';

    protected $guarded = [];

    protected $casts = [
        'date_session' => 'date',
        'solde_ouverture' => 'decimal:2',
        'solde_cloture_theorique' => 'decimal:2',
        'solde_cloture_reel' => 'decimal:2',
        'ecart' => 'decimal:2',
    ];

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementCaisse::class, 'session_caisse_id');
    }

    public function ouvreur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ouverte_par');
    }

    public function clotureur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cloturee_par');
    }
}
