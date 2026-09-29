<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupeFrais extends Model
{
    protected $table = 'groupes_frais';

    protected $guarded = [];

    public function typesRecurrents(): HasMany
    {
        return $this->hasMany(TypeFraisRecurrent::class);
    }

    public function fraisDivers(): HasMany
    {
        return $this->hasMany(CatalogueFraisDivers::class);
    }
}
