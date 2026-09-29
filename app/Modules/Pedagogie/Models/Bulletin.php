<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bulletin extends Model
{
    protected $fillable = [
        'eleve_id',
        'classe_id',
        'annee_scolaire_id',
        'periode_id',
        'moyenne_generale',
        'rang',
        'effectif_classe',
        'moyenne_classe_generale',
        'moyenne_plus_forte',
        'moyenne_plus_faible',
        'appreciation_generale',
        'decision_conseil',
        'mention',
        'nom_eleve',
        'nom_classe',
        'document_id',
        'document_template_id',
        'document_template_version',
        'generated_at',
        'rendered_html',
        'genere_par',
        'genere_le',
        'statut',
        'version',
        'soumis_par',
        'soumis_le',
        'valide_par',
        'valide_le',
        'publie_par',
        'publie_le',
    ];

    protected $casts = [
        'eleve_id' => 'integer',
        'classe_id' => 'integer',
        'annee_scolaire_id' => 'integer',
        'periode_id' => 'integer',
        'moyenne_generale' => 'float',
        'rang' => 'integer',
        'effectif_classe' => 'integer',
        'moyenne_classe_generale' => 'float',
        'moyenne_plus_forte' => 'float',
        'moyenne_plus_faible' => 'float',
        'document_id' => 'integer',
        'document_template_id' => 'integer',
        'document_template_version' => 'integer',
        'generated_at' => 'datetime',
        'genere_par' => 'integer',
        'genere_le' => 'datetime',
        'version' => 'integer',
        'soumis_le' => 'datetime',
        'valide_le' => 'datetime',
        'publie_le' => 'datetime',
    ];

    public function matieres(): HasMany
    {
        return $this->hasMany(BulletinMatiere::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BulletinVersion::class);
    }
}
