<?php

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;

class Seance extends Model
{
    protected $table = 'seances';

    protected $fillable = [
        'emploi_du_temps_id', 'date_seance', 'enseignant_id',
        'enseignant_prevu_id', 'enseignant_reel_id', 'salle_id',
        'programme_lecon_id', 'contenu_enseigne', 'devoirs_donnes',
        'motif_remplacement', 'motif_annulation', 'duree_minutes',
        'statut', 'progression_renseignee', 'appel_verrouille',
    ];

    protected $casts = [
        'date_seance' => 'date',
        'progression_renseignee' => 'boolean',
        'appel_verrouille' => 'boolean',
        'duree_minutes' => 'integer',
    ];

    /** Transitions de statut autorisées — évite qu'un bug fasse sauter une étape */
    public const TRANSITIONS_AUTORISEES = [
        'programmee' => ['commencee', 'annulee', 'reportee', 'remplacee', 'dispensee'],
        'commencee' => ['dispensee', 'non_dispensee', 'annulee'],
        'dispensee' => [],
        'annulee' => [],
        'reportee' => [],
        'non_dispensee' => [],
        'remplacee' => ['commencee', 'annulee', 'reportee', 'dispensee'],
    ];

    public function emploiDuTemps()
    {
        return $this->belongsTo(EmploiDuTemps::class);
    }

    public function lecon()
    {
        return $this->belongsTo(ProgrammeLecon::class, 'programme_lecon_id');
    }

    public function presences()
    {
        return $this->hasMany(Presence::class);
    }

    public function estVerrouillee(): bool
    {
        return (bool) $this->appel_verrouille;
    }

    public function estRemplacee(): bool
    {
        return ! empty($this->enseignant_prevu_id) && $this->enseignant_id !== $this->enseignant_prevu_id;
    }

    public function devoirs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Devoir::class);
    }

    public function progressions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Progression::class);
    }
}

