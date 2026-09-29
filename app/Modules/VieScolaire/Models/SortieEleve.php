<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Models;

use Illuminate\Database\Eloquent\Model;

class SortieEleve extends Model
{
    protected $table = 'sorties_eleves';

    public $timestamps = false;

    protected $fillable = [
        'eleve_id', 'parent_id', 'personne_autorisee_nom', 'type',
        'heure_sortie', 'justificatif_document_id', 'remis_par',
    ];

    protected $casts = [
        'heure_sortie' => 'datetime',
    ];

    // eleve_id, parent_id, remis_par : autres modules -> pas de relation
    // Eloquent directe, même règle que partout ailleurs dans le projet.
}
