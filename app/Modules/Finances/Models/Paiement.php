<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Paiement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new LogicException('Un paiement ne peut jamais être supprimé. Utilisez l’annulation motivée.');
        });
    }

    public function encaisseur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encaisse_par');
    }

    public function annulations(): HasMany
    {
        return $this->hasMany(PaiementAnnulation::class);
    }

    public function allocationsTranches(): HasMany
    {
        return $this->hasMany(PaiementTrancheAllocation::class);
    }

    public function fraisDivers(): BelongsTo
    {
        return $this->belongsTo(FraisDiversEleve::class, 'frais_divers_eleve_id');
    }
}
