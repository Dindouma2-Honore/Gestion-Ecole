<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TypePrime extends Model
{
    protected $table = 'types_primes';

    protected $fillable = [
        'code',
        'libelle',
        'mode_calcul',
        'valeur_defaut',
        'actif',
    ];

    protected static function booted(): void
    {
        static::creating(function (TypePrime $typePrime): void {
            if (filled($typePrime->code)) {
                return;
            }

            do {
                $code = 'PRIME-'.Str::upper(Str::random(8));
            } while (self::query()->where('code', $code)->exists());

            $typePrime->code = $code;
        });
    }

    protected function casts(): array
    {
        return [
            'valeur_defaut' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }

    public function primes(): HasMany
    {
        return $this->hasMany(Prime::class, 'type_prime_id');
    }
}
