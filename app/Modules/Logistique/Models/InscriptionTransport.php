<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InscriptionTransport extends Model
{
    protected $table = 'inscriptions_transport';

    public $timestamps = false;

    protected $fillable = [
        'eleve_id', 'circuit_id', 'arret_id', 'annee_scolaire_id', 'statut',
    ];

    public function arret(): BelongsTo
    {
        return $this->belongsTo(ArretCircuit::class, 'arret_id');
    }

    // eleve_id : module Scolarité -> pas de relation Eloquent directe,
    // même règle que partout ailleurs dans le projet. La méthode
    // getListeEleveParCircuit() charge quand même "eleve" avec `with()`
    // dans le Service : à remplacer par un appel explicite au contrat
    // Scolarité si le with() Eloquent inter-module s'avère problématique.
}
