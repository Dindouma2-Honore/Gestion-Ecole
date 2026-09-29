<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cycle extends Model
{
    protected $guarded = [];

    protected $casts = ['actif' => 'boolean'];

    public function niveaux(): HasMany
    {
        return $this->hasMany(Niveau::class);
    }
}
