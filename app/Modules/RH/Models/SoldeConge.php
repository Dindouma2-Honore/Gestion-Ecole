<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoldeConge extends Model
{
    protected $table = 'solde_conges';

    protected $fillable = [
        'employe_id',
        'annee_scolaire_id',
        'jours_acquis',
        'jours_pris',
    ];

    protected function casts(): array
    {
        return [
            'jours_acquis' => 'decimal:1',
            'jours_pris' => 'decimal:1',
        ];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'employe_id');
    }
}
