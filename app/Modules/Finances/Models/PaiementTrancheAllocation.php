<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementTrancheAllocation extends Model
{
    protected $guarded = [];

    protected $casts = ['montant' => 'decimal:2'];

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    public function tranche(): BelongsTo
    {
        return $this->belongsTo(TrancheFraisClasse::class, 'tranche_frais_classe_id');
    }
}
