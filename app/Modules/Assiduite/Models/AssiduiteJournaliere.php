<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssiduiteJournaliere extends Model
{
    protected $table = 'assiduite_journaliere';

    protected $guarded = [];

    protected $casts = ['date_assiduite' => 'date', 'details' => 'array', 'calcule_le' => 'datetime'];

    public function historiques(): HasMany
    {
        return $this->hasMany(AssiduiteHistorique::class);
    }
}
