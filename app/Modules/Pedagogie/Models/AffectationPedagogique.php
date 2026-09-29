<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffectationPedagogique extends Model
{
    protected $table = 'affectations_pedagogiques';

    protected $guarded = [];

    protected $casts = ['actif' => 'boolean', 'coefficient' => 'float'];

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function offre(): BelongsTo
    {
        return $this->belongsTo(OffrePedagogique::class, 'offre_pedagogique_id');
    }
}
