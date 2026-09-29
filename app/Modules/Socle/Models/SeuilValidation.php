<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;

class SeuilValidation extends Model
{
    protected $table = 'seuils_validation';

    protected $fillable = ['categorie_depense_id', 'montant_min', 'montant_max', 'role_validateur_requis'];

    protected $casts = [
        'montant_min' => 'decimal:2',
        'montant_max' => 'decimal:2',
    ];
}
