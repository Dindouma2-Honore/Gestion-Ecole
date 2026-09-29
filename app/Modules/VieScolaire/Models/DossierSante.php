<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Models;

use Illuminate\Database\Eloquent\Model;

class DossierSante extends Model
{
    public $timestamps = false;
    protected $table = 'dossiers_sante';

    protected $fillable = [
        'eleve_id', 'groupe_sanguin', 'allergies', 'maladies_chroniques',
        'medicaments_autorises', 'contact_urgence_nom', 'contact_urgence_telephone',
        'medecin_traitant', 'updated_at',
    ];
}
