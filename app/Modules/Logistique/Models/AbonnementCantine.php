<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;

class AbonnementCantine extends Model
{
    protected $table = 'abonnements_cantine';

    public $timestamps = false;

    protected $fillable = [
        'eleve_id', 'annee_scolaire_id', 'type', 'date_debut', 'date_fin',
        'montant', 'statut',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'montant' => 'decimal:2',
    ];
}
