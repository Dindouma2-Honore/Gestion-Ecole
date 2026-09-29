<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Remplacement extends Model
{
    protected $table = 'remplacements';

    protected $fillable = [
        'enseignant_absent_id',
        'enseignant_remplacant_id',
        'date_debut',
        'date_fin',
        'motif',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }

    public function enseignantAbsent(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class, 'enseignant_absent_id');
    }

    public function enseignantRemplacant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class, 'enseignant_remplacant_id');
    }
}
