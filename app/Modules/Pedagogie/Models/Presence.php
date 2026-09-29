<?php

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;

class Presence extends Model
{
    protected $table = 'presences';

    protected $fillable = [
        'seance_id', 'eleve_id', 'statut', 'motif', 'heure_arrivee',
        'justifie', 'document_justificatif_id', 'saisi_par',
    ];

    protected $casts = [
        'justifie' => 'boolean',
    ];

    public function seance()
    {
        return $this->belongsTo(Seance::class);
    }

    // eleve (Scolarité), document_justificatif (Socle), saisi_par (Socle) :
    // Models d'autres modules -> pas de relation directe, même règle.
}
