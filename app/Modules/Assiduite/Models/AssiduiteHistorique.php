<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Models;

use Illuminate\Database\Eloquent\Model;

class AssiduiteHistorique extends Model
{
    public $timestamps = false;

    protected $table = 'assiduite_historiques';

    protected $guarded = [];

    protected $casts = ['effectue_le' => 'datetime'];
}
