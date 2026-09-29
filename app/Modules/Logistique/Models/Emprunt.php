<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;

class Emprunt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'exemplaire_id', 'emprunteur_type', 'emprunteur_id', 'date_emprunt',
        'date_retour_prevue', 'date_retour_reelle', 'penalite',
    ];

    protected $casts = [
        'date_emprunt' => 'date',
        'date_retour_prevue' => 'date',
        'date_retour_reelle' => 'date',
        'penalite' => 'decimal:2',
    ];

    // emprunteur_type/emprunteur_id : relation polymorphique en simples
    // colonnes (Eleve ou Employe) — pas de morphTo Eloquent inter-module,
    // même convention que le reste du projet (cf. Visiteur en VieScolaire).
}
