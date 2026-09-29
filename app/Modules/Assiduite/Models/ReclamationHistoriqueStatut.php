<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReclamationHistoriqueStatut extends Model
{
    protected $table = 'reclamation_historique_statuts';

    protected $fillable = [
        'reclamation_id',
        'ancien_statut',
        'nouveau_statut',
        'modifie_par',
        'commentaire',
    ];

    public function reclamation(): BelongsTo
    {
        return $this->belongsTo(Reclamation::class, 'reclamation_id');
    }

    public function modifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par');
    }
}
