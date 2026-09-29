<?php

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Progression extends Model
{
    protected $table = 'progressions';

    protected $fillable = [
        'seance_id', 'chapitre_id', 'contenu_couvert', 'devoirs_donnes', 'saisi_par',
    ];

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function chapitre(): BelongsTo
    {
        return $this->belongsTo(ProgrammeChapitre::class, 'chapitre_id');
    }

    // seance_id et Seance, chapitre_id et ProgrammeChapitre : même module
    // (Pédagogie) -> relations Eloquent directes autorisées. saisi_par
    // référence `users` (module Socle) : pas de relation directe pour ce
    // champ.
}
