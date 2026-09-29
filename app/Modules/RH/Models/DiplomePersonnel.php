<?php

declare(strict_types=1);

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiplomePersonnel extends Model
{
    protected $table = 'diplomes_personnel';

    protected $guarded = [];

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'personnel_id');
    }
}
