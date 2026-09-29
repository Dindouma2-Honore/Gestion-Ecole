<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'nom', 'numero_identification', 'categorie', 'salle_id',
        'responsable_id', 'etat', 'date_acquisition', 'valeur_acquisition',
        'garantie_fin', 'date_mise_au_rebut',
    ];

    protected $casts = [
        'date_acquisition' => 'date',
        'garantie_fin' => 'date',
        'date_mise_au_rebut' => 'date',
        'valeur_acquisition' => 'decimal:2',
    ];

    public function historiqueLocalisations(): HasMany
    {
        return $this->hasMany(EquipementHistoriqueLocalisation::class);
    }

    // responsable_id : module Socle/RH (employés) -> pas de relation
    // Eloquent directe, même règle que partout ailleurs dans le projet.
}
