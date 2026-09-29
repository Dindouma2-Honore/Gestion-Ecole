<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;

class RelancePaiement extends Model
{
    protected $table = 'relances_paiement';

    protected $guarded = [];

    protected $casts = [
        'date_relance' => 'datetime',
        'reste_a_payer_constate' => 'decimal:2',
    ];
}
