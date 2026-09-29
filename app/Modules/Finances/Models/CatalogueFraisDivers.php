<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogueFraisDivers extends Model
{
    protected $table = 'catalogue_frais_divers';

    protected $guarded = [];

    protected $casts = ['montant_defaut' => 'decimal:2', 'actif' => 'boolean'];

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(GroupeFrais::class, 'groupe_frais_id');
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(FraisDiversEleve::class);
    }
}
