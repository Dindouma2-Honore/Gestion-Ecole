<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use App\Modules\Pedagogie\Enums\StatutProgressionElementEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Progression extends Model
{
    protected $table = 'progressions';

    protected $fillable = [
        'etat_avancement', 'observation',
        'seance_id', 'chapitre_id', 'contenu_couvert', 'chapitre_termine', 'devoirs_donnes', 'saisi_par',
    ];

    protected $casts = [
        'chapitre_termine' => 'boolean',
        'duree_minutes' => 'integer',
        'date_progression' => 'date',
    ];

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function chapitre(): BelongsTo
    {
        return $this->belongsTo(ProgrammeChapitre::class, 'chapitre_id');
    }

    public function lecon(): BelongsTo
    {
        return $this->belongsTo(ProgrammeLecon::class, 'lecon_id');
    }

    public function isTerminee(): bool
    {
        return $this->chapitre_termine || $this->statut_element === StatutProgressionElementEnum::TERMINEE->value || $this->statut_element === 'terminee';
    }

    // seance_id et Seance, chapitre_id et ProgrammeChapitre : même module
    // (Pédagogie) -> relations Eloquent directes autorisées. saisi_par
    // référence `users` (module Socle) : pas de relation directe pour ce
    // champ.
}
