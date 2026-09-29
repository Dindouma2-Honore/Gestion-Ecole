<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;

class FormatNumerotation extends Model
{
    protected $table = 'formats_numerotation';

    protected $fillable = [
        'type_document',
        'libelle',
        'format',
        'reinitialisation',
        'prochain_numero',
        'derniere_cle_compteur',
        'a_valider',
    ];

    protected $casts = [
        'a_valider' => 'boolean',
        'prochain_numero' => 'integer',
    ];
}
