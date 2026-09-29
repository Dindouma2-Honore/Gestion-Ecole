<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfigurationFraisClasse extends Model
{
    protected $table = 'configurations_frais_classe';

    protected $guarded = [];

    protected $casts = [
        'frais_inscription' => 'decimal:2',
        'montant_total' => 'decimal:2',
        'montant_minimum_inscription' => 'decimal:2',
        'actif' => 'boolean',
    ];

    public function tranches(): HasMany
    {
        return $this->hasMany(TrancheFraisClasse::class)->orderBy('ordre');
    }
}
