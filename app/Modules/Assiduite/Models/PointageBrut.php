<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Models;

use Illuminate\Database\Eloquent\Model;

class PointageBrut extends Model
{
    protected $table = 'pointages_bruts';

    protected $guarded = [];

    protected $casts = ['horodatage' => 'datetime', 'importe_le' => 'datetime', 'payload' => 'array'];
}
