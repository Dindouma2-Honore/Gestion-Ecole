<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Models;

use Illuminate\Database\Eloquent\Model;

class JustificationAssiduite extends Model
{
    protected $table = 'justifications_assiduite';

    protected $guarded = [];

    protected $casts = ['date_concernee' => 'date', 'soumis_le' => 'datetime', 'decide_le' => 'datetime'];
}
