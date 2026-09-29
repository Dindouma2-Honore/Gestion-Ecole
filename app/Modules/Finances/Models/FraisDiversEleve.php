<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraisDiversEleve extends Model
{
    protected $table = 'frais_divers_eleves';

    protected $guarded = [];

    protected $casts = ['montant' => 'decimal:2'];

    public function poste(): BelongsTo
    {
        return $this->belongsTo(CatalogueFraisDivers::class, 'catalogue_frais_divers_id');
    }
}
