<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La table `salles` a déjà été créée en D.26 (Emplois du temps) avec ses
 * colonnes minimales — ce Model et les colonnes type/capacite/niveau_id/etat
 * sont ajoutés ici, en H.59, qui est le module où `salles` est réellement
 * défini et enrichi. Ne pas recréer cette table depuis zéro si D.26 a été
 * codé en premier (cf. migration 0001_01_01 de ce module qui fait un ALTER
 * TABLE et non un CREATE TABLE).
 */
class Salle extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code', 'nom', 'batiment', 'zone', 'type', 'capacite', 'niveau_id',
        'responsable_id', 'etat', 'disponible', 'observations',
    ];

    protected $casts = ['disponible' => 'boolean'];

    public function equipements(): HasMany
    {
        return $this->hasMany(Equipement::class);
    }

    public function travaux(): HasMany
    {
        return $this->hasMany(TravauxInfrastructure::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(DemandeIntervention::class);
    }
}
