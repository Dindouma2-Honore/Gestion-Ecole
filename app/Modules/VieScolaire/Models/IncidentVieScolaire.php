<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncidentVieScolaire extends Model
{
    protected $table = 'incidents_vie_scolaire';

    protected $fillable = ['reference', 'categorie', 'date_incident', 'lieu', 'personne_concernee_type', 'personne_concernee_id', 'description', 'priorite', 'responsable_id', 'statut', 'resolution', 'cree_par'];

    protected $casts = ['date_incident' => 'datetime'];

    public function historiques(): HasMany
    {
        return $this->hasMany(IncidentVieScolaireHistorique::class, 'incident_id');
    }
}
