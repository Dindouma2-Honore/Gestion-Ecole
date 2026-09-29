<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;

class PresenceTransport extends Model
{
    protected $table = 'presences_transport';

    public $timestamps = false;

    protected $fillable = [
        'inscription_transport_id', 'date_trajet', 'trajet', 'present',
    ];

    protected $casts = [
        'date_trajet' => 'date',
        'present' => 'boolean',
    ];
}
