<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisiteInfirmerie extends Model
{
    protected $table = 'visites_infirmerie';

    protected $fillable = [
        'eleve_id',
        'date_heure',
        'motif',
        'soins_prodigues',
        'medicament_administre',
        'gravite',
        'evacuation_necessaire',
        'parent_notifie',
        'traite_par',
    ];

    protected $casts = [
        'date_heure' => 'datetime',
        'evacuation_necessaire' => 'boolean',
        'parent_notifie' => 'boolean',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo('App\Modules\Scolarite\Models\Eleve', 'eleve_id');
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }
}
