<?php

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;

class EmploiDuTemps extends Model
{
    protected $table = 'emplois_du_temps';

    protected $fillable = [
        'classe_id', 'matiere_id', 'enseignant_id',
        'salle_id', 'creneau_id', 'annee_scolaire_id', 'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function matiere()
    {
        return $this->belongsTo(Matiere::class);
    }

    public function creneau()
    {
        return $this->belongsTo(CreneauHoraire::class, 'creneau_id');
    }

    // classe, enseignant, salle, annee_scolaire : Models d'autres modules
    // (Scolarité, RH, Socle) -> pas de relation Eloquent directe ici, on
    // respecte la règle d'architecture. On récupère leurs données via les
    // Contracts de ces modules si besoin d'affichage.
}
