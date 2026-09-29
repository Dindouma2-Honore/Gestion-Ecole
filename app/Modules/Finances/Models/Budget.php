<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    protected $guarded = [];

    public function lignes(): HasMany
    {
        return $this->hasMany(BudgetLigne::class);
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }
}
