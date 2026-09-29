<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Models;

use Illuminate\Database\Eloquent\Model;

class VisiteInfirmerie extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'eleve_id', 'date_heure', 'motif', 'soins_prodigues',
        'medicament_administre', 'gravite', 'evacuation_necessaire',
        'parent_notifie', 'traite_par',
    ];

    protected $casts = [
        'date_heure' => 'datetime',
        'evacuation_necessaire' => 'boolean',
        'parent_notifie' => 'boolean',
    ];
}
