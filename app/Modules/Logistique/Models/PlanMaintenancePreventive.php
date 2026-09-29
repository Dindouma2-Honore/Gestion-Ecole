<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;

class PlanMaintenancePreventive extends Model
{
    protected $table = 'plans_maintenance_preventive';

    public $timestamps = false;

    protected $fillable = [
        'equipement_id', 'frequence_jours', 'derniere_execution', 'prochaine_echeance',
    ];

    protected $casts = [
        'derniere_execution' => 'date',
        'prochaine_echeance' => 'date',
    ];
}
