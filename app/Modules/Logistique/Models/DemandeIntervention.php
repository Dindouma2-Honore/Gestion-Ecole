<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandeIntervention extends Model
{
    public $timestamps = false;

    protected $table = 'demandes_intervention';

    protected $fillable = [
        'equipement_id', 'salle_id', 'description', 'type', 'type_panne', 'priorite', 'technicien_id',
        'technicien_externe_nom', 'diagnostic', 'cout', 'pieces_utilisees',
        'resultat', 'statut', 'date_signalement', 'date_intervention', 'date_reparation', 'date_cloture',
    ];

    protected $casts = [
        'date_signalement' => 'datetime',
        'date_intervention' => 'datetime',
        'date_reparation' => 'datetime',
        'date_cloture' => 'datetime',
        'cout' => 'decimal:2',
    ];

    public function equipement(): BelongsTo
    {
        return $this->belongsTo(Equipement::class);
    }

    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }
}
