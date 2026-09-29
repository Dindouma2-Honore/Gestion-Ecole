<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Models;

use Illuminate\Database\Eloquent\Model;

class Visiteur extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'nom', 'telephone', 'photo_path', 'motif',
        'personne_visitee_type', 'personne_visitee_id',
        'heure_entree', 'heure_sortie', 'badge_numero',
        'autorisation_prealable', 'incident_signale', 'enregistre_par',
    ];

    protected $casts = [
        'heure_entree' => 'datetime',
        'heure_sortie' => 'datetime',
        'autorisation_prealable' => 'boolean',
        'incident_signale' => 'boolean',
    ];
}
