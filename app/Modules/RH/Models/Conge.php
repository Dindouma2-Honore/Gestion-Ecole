<?php

namespace App\Modules\RH\Models;

use App\Modules\RH\Traits\HasWorkflowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conge extends Model
{
    use HasWorkflowStatus;

    protected $table = 'conges';

    protected $fillable = [
        'employe_id',
        'type',
        'date_debut',
        'date_fin',
        'nombre_jours',
        'motif',
        'justificatif_document_id',
        'statut',
        'remplacant_temporaire_id',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'nombre_jours' => 'decimal:1',
        ];
    }

    public function transitionsAutorisees(): array
    {
        return [
            'demande' => ['approuve', 'rejete'],
            'approuve' => ['en_cours', 'annule'],
            'en_cours' => ['termine'],
            'rejete' => [],
            'annule' => [],
            'termine' => [],
        ];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'employe_id');
    }

    public function remplacantTemporaire(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'remplacant_temporaire_id');
    }

    public function historiqueStatuts(): HasMany
    {
        return $this->hasMany(CongeHistoriqueStatut::class, 'conge_id');
    }
}
