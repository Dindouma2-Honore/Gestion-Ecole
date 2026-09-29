<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;

class TravauxInfrastructure extends Model
{
    protected $table = 'travaux_infrastructure';

    public $timestamps = false;

    protected $fillable = [
        'salle_id', 'description', 'date_debut', 'date_fin_prevue',
        'date_fin_reelle', 'cout', 'statut',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin_prevue' => 'date',
        'date_fin_reelle' => 'date',
    ];
}
