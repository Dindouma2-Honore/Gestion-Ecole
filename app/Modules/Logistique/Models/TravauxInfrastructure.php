<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravauxInfrastructure extends Model
{
    protected $table = 'travaux_infrastructure';

    public $timestamps = false;

    protected $fillable = [
        'salle_id', 'description', 'responsable', 'date_debut', 'date_fin_prevue',
        'date_fin_reelle', 'cout', 'budget_prevu', 'cout_reel', 'avancement',
        'pieces_justificatives', 'statut',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin_prevue' => 'date',
        'date_fin_reelle' => 'date',
    ];

    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }
}
