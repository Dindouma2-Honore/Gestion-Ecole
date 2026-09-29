<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JourFerie extends Model
{
    protected $table = 'jours_feries';

    protected $fillable = ['libelle', 'date', 'niveau_id', 'date_debut', 'date_fin', 'recurrent'];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'date' => 'date',
        'recurrent' => 'boolean',
    ];

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }
}
