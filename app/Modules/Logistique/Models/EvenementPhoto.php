<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;

class EvenementPhoto extends Model
{
    protected $table = 'evenement_photos';

    public $timestamps = false;

    protected $fillable = [
        'evenement_id', 'document_id',
    ];

    // document_id : module Socle (A.5) -> pas de relation Eloquent directe,
    // même règle que partout ailleurs dans le projet.
}
