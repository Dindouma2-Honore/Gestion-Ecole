<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;

class PresenceCantine extends Model
{
    protected $table = 'presences_cantine';

    public $timestamps = false;

    protected $fillable = [
        'abonnement_id', 'date_repas', 'present',
    ];

    protected $casts = [
        'date_repas' => 'date',
        'present' => 'boolean',
    ];
}
