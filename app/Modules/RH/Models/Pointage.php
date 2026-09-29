<?php

namespace App\Modules\RH\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pointage extends Model
{
    protected $table = 'pointages';

    protected $fillable = [
        'employe_id',
        'date_pointage',
        'heure_arrivee',
        'heure_depart',
        'mode_pointage',
        'terminal_id',
        'correction_manuelle',
        'corrige_par',
        'motif_correction',
    ];

    protected function casts(): array
    {
        return [
            'date_pointage' => 'date',
            'correction_manuelle' => 'boolean',
        ];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'employe_id');
    }

    public function correcteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrige_par');
    }
}
