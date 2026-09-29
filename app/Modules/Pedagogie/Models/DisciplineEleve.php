<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;

class DisciplineEleve extends Model
{
    protected $table = 'discipline_eleves';

    protected $fillable = ['eleve_id', 'classe_id', 'annee_scolaire_id', 'date_incident', 'type_incident', 'gravite', 'description', 'mesure_prise', 'confidentiel', 'enregistre_par'];

    protected $casts = ['date_incident' => 'date', 'confidentiel' => 'boolean'];
}
