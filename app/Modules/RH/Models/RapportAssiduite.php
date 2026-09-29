<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RapportAssiduite extends Model
{
    protected $table = 'rapports_assiduite';

    protected $fillable = [
        'employe_id',
        'mois',
        'annee',
        'jours_presents',
        'jours_retard',
        'jours_absence_justifiee',
        'jours_absence_non_justifiee',
        'heures_supplementaires',
    ];

    protected function casts(): array
    {
        return [
            'heures_supplementaires' => 'decimal:2',
        ];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'employe_id');
    }
}
