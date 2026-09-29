<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeHistoriqueCarriere extends Model
{
    protected $table = 'employe_historique_carriere';

    protected $fillable = [
        'employe_id',
        'evenement',
        'ancien_poste',
        'nouveau_poste',
        'date_evenement',
        'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'date_evenement' => 'date',
        ];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'employe_id');
    }
}
