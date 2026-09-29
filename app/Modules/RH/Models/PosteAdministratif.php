<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosteAdministratif extends Model
{
    protected $table = 'postes_administratifs';

    protected $guarded = [];

    protected $casts = ['actif' => 'boolean'];

    public function employes(): HasMany
    {
        return $this->hasMany(Employe::class);
    }
}
