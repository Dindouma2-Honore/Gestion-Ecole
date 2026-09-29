<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;

class PromessePaiement extends Model
{
    protected $table = 'promesses_paiement';

    protected $guarded = [];

    protected $casts = [
        'date_promesse' => 'date',
        'montant_promis' => 'decimal:2',
        'tenue' => 'boolean',
    ];
}
