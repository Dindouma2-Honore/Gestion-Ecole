<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;

class EquipementHistoriqueLocalisation extends Model
{
    protected $table = 'equipement_historique_localisations';

    public $timestamps = false;

    protected $fillable = [
        'equipement_id', 'ancienne_salle_id', 'nouvelle_salle_id', 'date_deplacement',
    ];

    protected $casts = [
        'date_deplacement' => 'date',
    ];
}
