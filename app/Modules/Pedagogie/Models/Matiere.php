<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Matiere extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'categorie_matiere_id',
        'code',
        'coefficient_defaut',
        'niveau_id',
        'coefficient',
        'description',
        'actif',
    ];

    protected $casts = [
        'coefficient' => 'float',
        'coefficient_defaut' => 'float',
        'niveau_id' => 'integer',
        'actif' => 'boolean',
    ];

    protected static function booted(): void
    {
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
