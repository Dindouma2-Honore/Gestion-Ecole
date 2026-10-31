<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;

class DecisionPedagogique extends Model
{
    protected $table = 'decisions_pedagogiques';

    protected $fillable = [
        'eleve_id',
        'classe_id',
        'annee_scolaire_id',
        'decision',
        'motif',
        'valide_par',
        'valide_le',
    ];

    protected $casts = [
        'eleve_id' => 'integer',
        'classe_id' => 'integer',
        'annee_scolaire_id' => 'integer',
        'valide_par' => 'integer',
        'valide_le' => 'datetime',
    ];

    public const DECISIONS_VALIDES = [
        'admis',
        'redouble',
        'exclu',
        'reoriente',
        'admis_sous_condition',
    ];
}
