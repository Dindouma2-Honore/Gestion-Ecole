<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Models;

use Illuminate\Database\Eloquent\Model;

class Visiteur extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'nom', 'prenom', 'telephone', 'photo_path', 'type_piece', 'numero_piece', 'motif',
        'personne_visitee_type', 'personne_visitee_id',
        'service_concerne', 'heure_entree', 'heure_sortie', 'badge_numero', 'badge_rendu',
        'autorisation_prealable', 'incident_signale', 'observations', 'observation_sortie', 'enregistre_par',
    ];

    protected $casts = [
        'heure_entree' => 'datetime',
        'heure_sortie' => 'datetime',
        'autorisation_prealable' => 'boolean',
        'incident_signale' => 'boolean',
        'badge_rendu' => 'boolean',
    ];
}
