<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;

class ArretCircuit extends Model
{
    protected $table = 'arrets_circuit';

    public $timestamps = false;

    protected $fillable = [
        'circuit_id', 'nom', 'ordre', 'heure_passage_matin', 'heure_passage_soir',
    ];
}
