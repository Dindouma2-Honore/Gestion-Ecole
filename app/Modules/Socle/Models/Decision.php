<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Decision extends Model
{
    protected $table = 'decisions';

    protected $guarded = [];

    protected $casts = [
        'echeance' => 'date',
    ];

    public function reunion(): BelongsTo
    {
        return $this->belongsTo(Reunion::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function tache(): BelongsTo
    {
        return $this->belongsTo(Tache::class);
    }

    public function getStatutAttribute(): string
    {
        return $this->tache?->statut ?? 'a_faire';
    }
}
