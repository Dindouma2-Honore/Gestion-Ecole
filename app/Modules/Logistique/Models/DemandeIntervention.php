<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;

class DemandeIntervention extends Model
{
    public $timestamps = false;
     protected $table = 'demandes_intervention';
    protected $fillable = [
        'equipement_id', 'salle_id', 'description', 'type', 'technicien_id',
        'technicien_externe_nom', 'diagnostic', 'cout', 'pieces_utilisees',
        'statut', 'date_signalement', 'date_reparation',
    ];

    protected $casts = [
        'date_signalement' => 'datetime',
        'date_reparation' => 'datetime',
        'cout' => 'decimal:2',
    ];
}
