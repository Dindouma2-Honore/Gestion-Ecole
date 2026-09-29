<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DossierSante extends Model
{
    protected $table = 'dossiers_sante';

    protected $fillable = [
        'eleve_id',
        'groupe_sanguin',
        'allergies',
        'maladies_chroniques',
        'medicaments_autorises',
        'contact_urgence_nom',
        'contact_urgence_telephone',
        'medecin_traitant',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo('App\Modules\Scolarite\Models\Eleve', 'eleve_id');
    }
}
