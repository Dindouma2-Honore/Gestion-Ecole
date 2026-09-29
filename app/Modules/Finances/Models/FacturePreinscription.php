<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacturePreinscription extends Model
{
    protected $table = 'factures_preinscription';

    protected $guarded = [];

    protected $casts = [
        'montant_total' => 'decimal:2',
        'montant_versement_prevu' => 'decimal:2',
        'payee_le' => 'datetime',
        'envoyee_le' => 'datetime',
    ];

    public function lignes(): HasMany
    {
        return $this->hasMany(FacturePreinscriptionLigne::class);
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par');
    }
}
