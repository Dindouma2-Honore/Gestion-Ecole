<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Fonctionnalite extends Model
{
    protected $table = 'catalogue_fonctionnalites';

    protected $fillable = ['code', 'nom', 'description', 'categorie', 'module_technique', 'parent_id', 'ordre', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('ordre');
    }

    public function habilitations(): HasMany
    {
        return $this->hasMany(HabilitationRoleFonctionnalite::class, 'fonctionnalite_id');
    }

    /** Public Feature alias for legacy module_technique. */
    protected function module(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->module_technique);
    }

    /** Public Feature alias for legacy nom. */
    protected function name(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->nom);
    }

    /** Public Feature alias for legacy actif. */
    protected function isActive(): Attribute
    {
        return Attribute::make(get: fn (): bool => (bool) $this->actif);
    }

    protected static function booted(): void
    {
        static::saving(function (self $feature): void {
            if (! preg_match('/^[a-z0-9_]+(?:\.[a-z0-9_]+)+$/', $feature->code)) {
                throw new InvalidArgumentException('Le code d’une fonctionnalité doit respecter la convention module.resource.');
            }
        });
    }
}
