<?php

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;

class Seance extends Model
{
    protected $table = 'seances';

    protected $fillable = [
        'emploi_du_temps_id', 'date_seance', 'enseignant_id',
        'statut', 'progression_renseignee',
    ];

    protected $casts = [
        'date_seance' => 'date',
        'progression_renseignee' => 'boolean',
    ];

    /** Transitions de statut autorisées — évite qu'un bug fasse sauter une étape */
    public const TRANSITIONS_AUTORISEES = [
        'programmee' => ['commencee', 'annulee', 'reportee'],
        'commencee' => ['dispensee', 'non_dispensee', 'annulee'],
        'dispensee' => [],
        'annulee' => [],
        'reportee' => [],
        'non_dispensee' => [],
    ];

    public function emploiDuTemps()
    {
        return $this->belongsTo(EmploiDuTemps::class);
    }

    // enseignant : Model du module RH -> pas de relation directe, on respecte
    // la règle d'architecture (accès via EnseignantServiceInterface).
}
