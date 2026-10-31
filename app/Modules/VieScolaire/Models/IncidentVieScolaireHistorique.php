<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentVieScolaireHistorique extends Model
{
    public $timestamps = false;

    protected $table = 'incident_vie_scolaire_historiques';

    protected $fillable = ['incident_id', 'ancien_statut', 'nouveau_statut', 'modifie_par', 'motif', 'modifie_le'];

    protected $casts = ['modifie_le' => 'datetime'];
}
