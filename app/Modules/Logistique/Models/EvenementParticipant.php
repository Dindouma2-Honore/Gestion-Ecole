<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;

class EvenementParticipant extends Model
{
    protected $table = 'evenement_participants';

    public $timestamps = false;

    protected $fillable = [
        'evenement_id', 'participant_type', 'participant_id',
        'autorisation_parentale_recue', 'document_autorisation_id',
    ];

    protected $casts = [
        'autorisation_parentale_recue' => 'boolean',
    ];

    // participant_type/participant_id : relation polymorphique en simples
    // colonnes (Eleve ou Employe) — pas de morphTo Eloquent inter-module,
    // même convention que le reste du projet.
}
