<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrancheFraisClasse extends Model
{
    protected $table = 'tranches_frais_classe';

    protected $guarded = [];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_echeance' => 'date',
        'actif' => 'boolean',
    ];

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(ConfigurationFraisClasse::class, 'configuration_frais_classe_id');
    }
}
