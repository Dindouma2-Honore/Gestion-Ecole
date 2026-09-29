<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Niveau extends Model
{
    protected $table = 'niveaux';

    protected $guarded = [];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }
}
