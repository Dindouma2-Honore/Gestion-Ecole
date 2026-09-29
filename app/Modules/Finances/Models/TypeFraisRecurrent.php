<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TypeFraisRecurrent extends Model
{
    protected $table = 'types_frais_recurrents';

    protected $guarded = [];

    protected $casts = ['montant' => 'decimal:2', 'ratio_tranche_1' => 'decimal:2', 'actif' => 'boolean'];

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(GroupeFrais::class, 'groupe_frais_id');
    }

    public function grille(): HasOne
    {
        return $this->hasOne(GrilleFrais::class);
    }
}
