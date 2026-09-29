<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelPosteHistorique extends Model
{
    protected $table = 'personnel_postes_historique';

    protected $guarded = [];

    protected $casts = ['date_debut' => 'date', 'date_fin' => 'date'];
}
