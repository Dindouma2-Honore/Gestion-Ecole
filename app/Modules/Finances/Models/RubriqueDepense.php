<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RubriqueDepense extends Model
{
    protected $table = 'rubriques_depenses';

    protected $guarded = [];

    protected $casts = ['active' => 'boolean'];

    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class);
    }
}
