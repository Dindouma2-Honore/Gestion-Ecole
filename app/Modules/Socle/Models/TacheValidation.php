<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TacheValidation extends Model
{
    protected $table = 'tache_validations';

    protected $guarded = [];

    protected $casts = [
        'validated_at' => 'datetime',
    ];

    public function tache(): BelongsTo
    {
        return $this->belongsTo(Tache::class);
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validateur_id');
    }
}
