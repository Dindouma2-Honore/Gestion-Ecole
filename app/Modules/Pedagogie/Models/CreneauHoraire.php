<?php

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;

class CreneauHoraire extends Model
{
    protected $table = 'creneaux_horaires';

    protected $fillable = ['jour_semaine', 'heure_debut', 'heure_fin'];

    protected $casts = [
        'jour_semaine' => 'integer',
    ];
}
