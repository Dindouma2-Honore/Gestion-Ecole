<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Models;

use Illuminate\Database\Eloquent\Model;

class ReclamationHistoriqueStatut extends Model
{
    public $timestamps = false;

    protected $fillable = ['reclamation_id', 'statut', 'changed_by', 'commentaire', 'changed_at'];

    protected $casts = ['changed_at' => 'datetime'];
}
