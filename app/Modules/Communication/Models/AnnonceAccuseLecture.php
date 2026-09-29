<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnonceAccuseLecture extends Model
{
    protected $table = 'annonce_accuses_lecture';

    protected $fillable = [
        'annonce_id',
        'user_id',
        'lu_le',
    ];

    protected $casts = [
        'lu_le' => 'datetime',
    ];

    public function annonce(): BelongsTo
    {
        return $this->belongsTo(Annonce::class, 'annonce_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
