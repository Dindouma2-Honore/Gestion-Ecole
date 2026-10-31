<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PalierAnciennete extends Model
{
    protected $table = 'paliers_anciennete';

    protected $guarded = [];

    protected $casts = ['actif' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (self $palier): void {
            $min = (int) $palier->anciennete_min_mois;
            $max = $palier->anciennete_max_mois === null ? null : (int) $palier->anciennete_max_mois;
            if ($max !== null && $max < $min) {
                throw ValidationException::withMessages(['anciennete_max_mois' => 'Le maximum doit être supérieur ou égal au minimum.']);
            }
            $overlap = static::query()->where('actif', true)->when($palier->exists, fn ($q) => $q->whereKeyNot($palier->getKey()))
                ->where(fn ($q) => $q->whereNull('anciennete_max_mois')->orWhere('anciennete_max_mois', '>=', $min))
                ->when($max !== null, fn ($q) => $q->where('anciennete_min_mois', '<=', $max))->exists();
            if ($palier->actif && $overlap) {
                throw ValidationException::withMessages(['anciennete_min_mois' => 'Cet intervalle chevauche un autre palier actif.']);
            }
        });
    }
}
