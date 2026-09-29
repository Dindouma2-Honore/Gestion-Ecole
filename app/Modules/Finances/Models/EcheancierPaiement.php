<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcheancierPaiement extends Model
{
    protected $table = 'echeanciers_paiement';

    protected $guarded = [];

    protected $casts = [
        'date_echeance' => 'date',
        'montant' => 'decimal:2',
    ];

    public function grilleFrais(): BelongsTo
    {
        return $this->belongsTo(GrilleFrais::class);
    }
}
