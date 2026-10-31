<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Models;

use Illuminate\Database\Eloquent\Model;

class Filiere extends Model
{
    protected $table = 'filieres';

    protected $guarded = [];

    protected $casts = ['actif' => 'boolean'];
}
