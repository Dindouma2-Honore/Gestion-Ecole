<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipementHistoriqueStatut extends Model
{
    protected $table = 'equipement_historique_statuts';

    protected $fillable = [
        'equipement_id',
        'ancien_statut',
        'nouveau_statut',
        'motif',
        'effectue_par',
    ];

    public function equipement(): BelongsTo
    {
        return $this->belongsTo(Equipement::class);
    }
}
