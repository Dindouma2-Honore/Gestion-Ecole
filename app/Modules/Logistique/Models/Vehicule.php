<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicule extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'immatriculation', 'capacite', 'etat',
    ];
}
