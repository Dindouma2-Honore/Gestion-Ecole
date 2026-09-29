<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Depense extends Model
{
    protected $table = 'depenses';

    protected $guarded = [];

    protected $casts = ['montant' => 'decimal:2', 'date_depense' => 'date', 'validee_le' => 'datetime', 'payee_le' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Une dépense financière ne peut pas être supprimée physiquement.'));
    }

    public function rubrique(): BelongsTo
    {
        return $this->belongsTo(RubriqueDepense::class, 'rubrique_depense_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }
}
