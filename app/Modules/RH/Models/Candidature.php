<?php

declare(strict_types=1);

namespace App\Modules\RH\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Candidature extends Model
{
    protected $table = 'candidatures';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_entretien' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $candidature): void {
            $candidature->reference ??= 'CAND-'.now()->format('Y').'-'.Str::upper(Str::random(6));
        });
    }

    public function evaluateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluee_par');
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function getNomCompletAttribute(): string
    {
        return trim($this->nom.' '.$this->prenom);
    }
}
