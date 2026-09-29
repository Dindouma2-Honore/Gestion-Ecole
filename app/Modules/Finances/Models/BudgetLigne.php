<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLigne extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['montant_prevu' => 'decimal:2'];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(BudgetCategorie::class, 'categorie_id');
    }
}
