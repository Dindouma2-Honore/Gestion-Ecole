<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;

/** Projection financière en lecture seule d'un élève inscrit. */
final class EleveFinancier extends Model
{
    protected $table = 'eleves';

    public $timestamps = false;

    protected $guarded = ['*'];
}
