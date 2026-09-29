<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;

class AbsencePersonnel extends Model
{
    protected $table = 'absences_personnel';

    protected $guarded = [];

    protected $casts = ['date_debut' => 'date', 'date_fin' => 'date', 'date_validation' => 'datetime'];

    public function employe()
    {
        return $this->belongsTo(Employe::class);
    }
}
