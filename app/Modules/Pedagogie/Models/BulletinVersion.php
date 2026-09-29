<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulletinVersion extends Model
{
    protected $fillable = ['bulletin_id', 'version', 'donnees', 'motif', 'cree_par'];

    protected $casts = ['donnees' => 'array', 'version' => 'integer'];

    public function bulletin(): BelongsTo
    {
        return $this->belongsTo(Bulletin::class);
    }
}
