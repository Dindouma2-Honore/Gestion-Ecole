<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementAnnulation extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'annule_le' => 'datetime',
    ];

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annule_par');
    }
}
