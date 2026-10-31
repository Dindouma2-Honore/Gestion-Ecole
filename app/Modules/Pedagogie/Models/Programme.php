<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use App\Modules\Pedagogie\Enums\StatutProgrammeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programme extends Model
{
    use HasFactory;

    protected $fillable = [
        'origine_programme_id', 'version', 'analyse_statut', 'analyse_erreur', 'importe_le', 'valide_le',
        'matiere_id',
        'offre_pedagogique_id',
        'niveau_id',
        'classe_id',
        'annee_scolaire_id',
        'source',
        'enseignant_id',
        'document_source_id',
        'salle_id',
        'valide_par',
        'date_validation',
        'motif_rejet',
        'metadata_analyse',
        'erreur_analyse',
        'titre',
        'description',
        'ordre',
        'version',
        'programme_parent_id',
        'statut',
    ];

    protected $casts = [
        'ordre' => 'integer',
        'version' => 'integer',
        'programme_parent_id' => 'integer',
        'matiere_id' => 'integer',
        'offre_pedagogique_id' => 'integer',
        'niveau_id' => 'integer',
        'classe_id' => 'integer',
        'annee_scolaire_id' => 'integer',
        'enseignant_id' => 'integer',
        'document_source_id' => 'integer',
        'salle_id' => 'integer',
        'valide_par' => 'integer',
        'date_validation' => 'datetime',
        'metadata_analyse' => 'array',
    ];

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function offrePedagogique(): BelongsTo
    {
        return $this->belongsTo(OffrePedagogique::class, 'offre_pedagogique_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'programme_parent_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'programme_parent_id')->orderBy('version', 'desc');
    }

    public function chapitres(): HasMany
    {
        return $this->hasMany(ProgrammeChapitre::class, 'programme_id')->orderBy('ordre', 'asc');
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(ProgrammeAnalyse::class, 'programme_id')->orderBy('id', 'desc');
    }

    public function derniereAnalyse(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProgrammeAnalyse::class, 'programme_id')->latestOfMany();
    }

    public function isValide(): bool
    {
        return in_array($this->statut, [StatutProgrammeEnum::VALIDE->value, 'valide', StatutProgrammeEnum::PUBLIE->value, 'publie'], true);
    }

    public function isArchive(): bool
    {
        return in_array($this->statut, [StatutProgrammeEnum::ARCHIVE->value, 'archive'], true);
    }

    // Pas de relation Eloquent vers Niveau ou AnneeScolaire : ces tables
    // appartiennent à d'autres modules (Scolarité / Socle). On ne référence
    // que leur ID ; toute lecture de détail passe par leur Contract.
}
