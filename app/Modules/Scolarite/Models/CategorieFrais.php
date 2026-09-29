<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategorieFrais extends Model
{
    protected $table = 'categories_frais';

    protected $fillable = ['nom'];

    public function frais(): HasMany
    {
        return $this->hasMany(Frais::class, 'categorie_frais_id');
    }
}
