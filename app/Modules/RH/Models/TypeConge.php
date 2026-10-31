<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TypeConge extends Model
{
    protected $table = 'types_conges';

    protected $fillable = [
        'code',
        'nom',
        'description',
        'remunere',
        'impact_paie',
        'decompte_solde',
        'justificatif_obligatoire',
        'validation_requise',
        'methode_deduction',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'remunere' => 'boolean',
            'decompte_solde' => 'boolean',
            'justificatif_obligatoire' => 'boolean',
            'validation_requise' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $typeConge): void {
            $baseCode = Str::slug((string) $typeConge->nom);
            $code = $baseCode;
            $suffix = 2;

            while (static::query()->where('code', $code)->exists()) {
                $code = $baseCode.'-'.$suffix++;
            }

            $typeConge->code = $code;
        });
    }

    public function conges(): HasMany
    {
        return $this->hasMany(Conge::class, 'type', 'code');
    }
}
