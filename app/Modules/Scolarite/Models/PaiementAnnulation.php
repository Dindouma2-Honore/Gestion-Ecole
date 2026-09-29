<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementAnnulation extends Model
{
    protected $table = 'paiement_annulations_scolarite';

    public $timestamps = false;

    protected $fillable = ['paiement_id', 'motif', 'annule_par', 'annule_le'];

    protected $casts = [
        'annule_le' => 'datetime',
    ];

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }
}
