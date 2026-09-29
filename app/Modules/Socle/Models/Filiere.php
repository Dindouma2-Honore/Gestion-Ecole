<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;

class Filiere extends Model
{
    protected $guarded = [];

    protected $casts = ['actif' => 'boolean'];
}
