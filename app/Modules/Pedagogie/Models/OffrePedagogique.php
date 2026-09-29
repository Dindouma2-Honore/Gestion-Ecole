<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OffrePedagogique extends Model
{
    protected $table = 'offres_pedagogiques';

    protected $guarded = [];

    protected $casts = ['coefficient_matiere' => 'decimal:2', 'volume_horaire' => 'decimal:2', 'heures_hebdomadaires' => 'decimal:2', 'actif' => 'boolean'];

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }
}
