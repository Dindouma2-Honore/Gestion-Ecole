<?php

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;

class EmploiDuTemps extends Model
{
    public const STATUT_PREPARATION = 'preparation';
    public const STATUT_GENERE = 'genere';
    public const STATUT_AJUSTE = 'ajuste';
    public const STATUT_VALIDE = 'valide';
    public const STATUT_PUBLIE = 'publie';

    protected $table = 'emplois_du_temps';

    protected $fillable = [
        'classe_id', 'matiere_id', 'libelle_activite', 'enseignant_id',
        'salle_id', 'creneau_id', 'annee_scolaire_id', 'actif',
        'statut', 'publie_par', 'publie_le',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'publie_le' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $emploi): void {
            // Compatibilité avec les intégrations historiques qui créaient
            // directement une grille active sans préciser son état de workflow.
            if (! array_key_exists('statut', $emploi->getAttributes()) && $emploi->actif) {
                $emploi->statut = 'publie';
            }
        });
    }

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
