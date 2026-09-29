<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementRepartition extends Model
{
    protected $table = 'paiement_repartitions';

    protected $fillable = ['paiement_id', 'frais_eleve_id', 'montant_alloue'];

    protected $casts = [
        'montant_alloue' => 'decimal:2',
    ];

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    public function fraisEleve(): BelongsTo
    {
        return $this->belongsTo(FraisEleve::class, 'frais_eleve_id');
    }
}
