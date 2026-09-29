<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Programme extends Model
{
    use HasFactory;

    protected $fillable = [
        'matiere_id',
        'niveau_id',
        'classe_id',
        'annee_scolaire_id',
        'source',
        'enseignant_id',
        'document_source_id',
        'salle_id',
        'valide_par',
        'motif_rejet',
        'titre',
        'description',
        'ordre',
        'statut',
    ];

    protected $casts = [
        'ordre' => 'integer',
        'matiere_id' => 'integer',
        'niveau_id' => 'integer',
        'classe_id' => 'integer',
        'annee_scolaire_id' => 'integer',
        'enseignant_id' => 'integer',
        'document_source_id' => 'integer',
        'salle_id' => 'integer',
        'valide_par' => 'integer',
    ];

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function chapitres(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProgrammeChapitre::class, 'programme_id')->orderBy('ordre', 'asc');
    }

    // Pas de relation Eloquent vers Niveau ou AnneeScolaire : ces tables
    // appartiennent à d'autres modules (Scolarité / Socle). On ne référence
    // que leur ID ; toute lecture de détail passe par leur Contract.
}
