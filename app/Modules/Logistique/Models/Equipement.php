<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Equipement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'nom', 'numero_identification', 'categorie', 'categorie_id', 'salle_id',
        'responsable_id', 'fournisseur', 'numero_serie', 'etat', 'statut_operationnel', 'date_acquisition',
        'valeur_acquisition', 'garantie_fin', 'observations', 'date_mise_au_rebut',
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

    public function historiqueStatuts(): HasMany
    {
        return $this->hasMany(EquipementHistoriqueStatut::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(EquipementPhoto::class);
    }

    public function photoPrincipale(): HasOne
    {
        return $this->hasOne(EquipementPhoto::class)->where('principal', true);
    }

    /**
     * Catégorie du catalogue (matériel). La colonne legacy `categorie`
     * (texte libre) reste prioritaire sur l'accès par propriété :
     * `$equipement->categorie` renvoie toujours la valeur legacy, la relation
     * s'interroge par `$equipement->categorie()->first()`.
     */
    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieMateriel::class, 'categorie_id');
    }

    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(DemandeIntervention::class);
    }

    public function maintenancesPreventives(): HasMany
    {
        return $this->hasMany(PlanMaintenancePreventive::class);
    }

    // responsable_id : module Socle/RH (employés) -> pas de relation
    // Eloquent directe, même règle que partout ailleurs dans le projet.
}
