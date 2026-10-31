<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Models;

use Illuminate\Database\Eloquent\Model;

class VisiteInfirmerie extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'eleve_id', 'date_heure', 'motif', 'symptomes', 'soins_prodigues',
        'medicament_administre', 'dose_administree', 'gravite', 'decision', 'observations',
        'evacuation_necessaire', 'parent_notifie', 'canal_notification_parent',
        'parent_notifie_le', 'commentaire_notification', 'traite_par',
    ];

    protected $casts = [
        'date_heure' => 'datetime',
        'evacuation_necessaire' => 'boolean',
        'parent_notifie' => 'boolean',
        'parent_notifie_le' => 'datetime',
    ];
}
