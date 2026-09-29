<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentTransport extends Model
{
    protected $table = 'incidents_transport';

    public $timestamps = false;

    protected $fillable = [
        'circuit_id', 'description', 'date_incident', 'gravite',
    ];

    protected $casts = [
        'date_incident' => 'datetime',
    ];
}
