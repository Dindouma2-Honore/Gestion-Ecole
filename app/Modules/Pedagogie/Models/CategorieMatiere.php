<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategorieMatiere extends Model
{
    protected $table = 'categories_matieres';

    protected $guarded = [];

    protected $casts = ['actif' => 'boolean'];

    public function matieres(): HasMany
    {
        return $this->hasMany(Matiere::class);
    }
}
