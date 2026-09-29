<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;

class ExemplaireLivre extends Model
{
    protected $table = 'exemplaires_livres';

    public $timestamps = false;

    protected $fillable = [
        'livre_id', 'code_exemplaire', 'etat', 'disponible',
    ];

    protected $casts = [
        'disponible' => 'boolean',
    ];
}
