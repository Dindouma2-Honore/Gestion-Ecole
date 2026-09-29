<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturePreinscriptionLigne extends Model
{
    protected $guarded = [];

    protected $casts = ['montant' => 'decimal:2', 'obligatoire' => 'boolean'];

    public function facture(): BelongsTo
    {
        return $this->belongsTo(FacturePreinscription::class, 'facture_preinscription_id');
    }

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(GroupeFrais::class, 'groupe_frais_id');
    }
}
