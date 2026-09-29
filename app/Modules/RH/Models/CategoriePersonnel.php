<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CategoriePersonnel extends Model
{
    protected $table = 'categories_personnel';

    protected $guarded = [];

    protected $casts = ['actif' => 'boolean', 'progression_automatique' => 'boolean'];

    public function employes(): BelongsToMany
    {
        return $this->belongsToMany(Employe::class, 'personnel_categorie', 'categorie_personnel_id', 'employe_id');
    }
}
