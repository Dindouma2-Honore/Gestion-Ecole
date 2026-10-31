<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Matiere extends Model
{
    public const TYPE_MATIERE = 'matiere';

    public const TYPE_DOMAINE_ACTIVITE = 'domaine_activite';

    use HasFactory;

    protected $fillable = [
        'nom',
        'nature',
        'categorie_matiere_id',
        'code',
        'coefficient_defaut',
        'coefficient',
        'description',
        'actif',
    ];

    public function scopeMatieres(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('type', self::TYPE_MATIERE);
    }

    public function scopeDomaines(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('type', self::TYPE_DOMAINE_ACTIVITE);
    }

    protected $casts = [
        'coefficient' => 'float',
        'coefficient_defaut' => 'float',
        'actif' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Matiere $matiere): void {
            if (filled($matiere->code)) {
                return;
            }

            $baseCode = Str::upper(Str::slug((string) $matiere->nom, '_'));
            $baseCode = substr($baseCode !== '' ? $baseCode : 'MATIERE', 0, 20);
            $code = $baseCode;
            $suffix = 2;

            while (static::query()->where('code', $code)->exists()) {
                $suffixText = '_'.$suffix++;
                $code = substr($baseCode, 0, 20 - strlen($suffixText)).$suffixText;
            }

            $matiere->code = $code;
        });

        static::creating(function (Matiere $matiere): void {
            $matiere->categorie_matiere_id ??= CategorieMatiere::query()
                ->where('code', 'NON_CLASSEE')->value('id');
        });
    }

    public function programmes(): HasMany
    {
        return $this->hasMany(Programme::class);
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieMatiere::class, 'categorie_matiere_id');
    }

    public function offresPedagogiques(): HasMany
    {
        return $this->hasMany(OffrePedagogique::class);
    }
}
