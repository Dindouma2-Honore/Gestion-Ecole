<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnseignantMatiereNiveau extends Model
{
    protected $table = 'enseignant_matiere_niveau';

    protected $fillable = [
        'enseignant_id',
        'matiere_id',
        'niveau_id',
        'annee_scolaire_id',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class, 'enseignant_id');
    }
}
