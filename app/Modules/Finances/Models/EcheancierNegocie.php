<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcheancierNegocie extends Model
{
    protected $table = 'echeanciers_negocies';

    protected $guarded = [];

    protected $casts = [
        'date_premiere_tranche' => 'date',
        'montant_total' => 'decimal:2',
    ];

    public function approbateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approuve_par');
    }
}
