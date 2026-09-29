<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Visiteur extends Model
{
    protected $table = 'visiteurs';

    protected $fillable = [
        'nom',
        'telephone',
        'photo_path',
        'motif',
        'personne_visitee_type',
        'personne_visitee_id',
        'heure_entree',
        'heure_sortie',
        'badge_numero',
        'autorisation_prealable',
        'incident_signale',
        'enregistre_par',
    ];

    protected $casts = [
        'heure_entree' => 'datetime',
        'heure_sortie' => 'datetime',
        'autorisation_prealable' => 'boolean',
        'incident_signale' => 'boolean',
    ];

    public function personneVisitee(): MorphTo
    {
        return $this->morphTo();
    }

    public function enregistrePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enregistre_par');
    }
}
