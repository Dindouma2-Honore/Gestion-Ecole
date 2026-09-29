<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contrat extends Model
{
    protected $table = 'contrats';

    protected $fillable = [
        'employe_id',
        'type',
        'categorie_paie',
        'grille_salariale_id',
        'matiere_paie_id',
        'tache_administrative',
        'date_debut',
        'date_fin',
        'periode_essai_fin',
        'salaire_base',
        'taux_horaire',
        'statut',
        'document_id',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'periode_essai_fin' => 'date',
            'salaire_base' => 'decimal:2',
            'taux_horaire' => 'decimal:2',
        ];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'employe_id');
    }

    public function avenants(): HasMany
    {
        return $this->hasMany(ContratAvenant::class, 'contrat_id');
    }

    public function bulletins(): HasMany
    {
        return $this->hasMany(BulletinPaie::class, 'contrat_id');
    }
}
