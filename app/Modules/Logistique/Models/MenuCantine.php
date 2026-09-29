<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;

class MenuCantine extends Model
{
    protected $table = 'menus_cantine';

    public $timestamps = false;

    protected $fillable = [
        'date_menu', 'description', 'allergenes',
    ];

    protected $casts = [
        'date_menu' => 'date',
    ];
}
