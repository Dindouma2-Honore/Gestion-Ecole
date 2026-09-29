<?php

namespace App\Modules\RH\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prime extends Model
{
    protected $table = 'primes';

    protected $fillable = [
        'employe_id',
        'type_prime_id',
        'montant',
        'mois',
        'annee',
        'justification',
        'statut',
        'proposee_par',
        'validee_par',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
        ];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'employe_id');
    }

    public function typePrime(): BelongsTo
    {
        return $this->belongsTo(TypePrime::class, 'type_prime_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposee_par');
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par');
    }
}
