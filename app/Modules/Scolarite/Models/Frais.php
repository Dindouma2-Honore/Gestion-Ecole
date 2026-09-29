<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Frais extends Model
{
    protected $table = 'frais';

    protected $fillable = [
        'nom', 'montant', 'categorie_frais_id', 'utilise_grille_tarifaire', 'ordre_repartition',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'utilise_grille_tarifaire' => 'boolean',
        'ordre_repartition' => 'integer',
    ];

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieFrais::class, 'categorie_frais_id');
    }

    public function grilleTarifaire(): HasMany
    {
        return $this->hasMany(GrilleTarifaire::class);
    }
}
