<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosteAdministratif extends Model
{
    protected $table = 'postes_administratifs';

    protected $guarded = [];

    protected $casts = [
        'actif' => 'boolean',
        'niveau_hierarchique' => 'integer',
        'ordre' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('ordre')->orderBy('nom');
    }

    public function employes(): HasMany
    {
        return $this->hasMany(Employe::class);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id')->orderBy('ordre')->orderBy('nom');
    }

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('actif', true);
    }
}
