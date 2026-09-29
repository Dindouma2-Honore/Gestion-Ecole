<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelPrime extends Model
{
    protected $guarded = [];

    protected $casts = ['valeur_override' => 'decimal:2', 'date_attribution' => 'date', 'date_fin' => 'date', 'actif' => 'boolean'];

    public function typePrime()
    {
        return $this->belongsTo(TypePrime::class);
    }

    public function employe()
    {
        return $this->belongsTo(Employe::class);
    }
}
