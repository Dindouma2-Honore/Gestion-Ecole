<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    protected $fillable = [
        'titre',
        'matiere_id',
        'classe_id',
        'annee_scolaire_id',
        'periode_id',
        'enseignant_id',
        'type_evaluation',
        'date_evaluation',
        'bareme',
        'coefficient_evaluation',
        'created_by',
        'statut',
        'document_sujet_id',
        'valide_par',
        'motif_rejet',
    ];

    protected $casts = [
        'date_evaluation' => 'date',
        'bareme' => 'float',
        'coefficient_evaluation' => 'float',
        'document_sujet_id' => 'integer',
        'valide_par' => 'integer',
    ];

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function estValidee(): bool
    {
        return $this->statut === 'valide';
    }
}
