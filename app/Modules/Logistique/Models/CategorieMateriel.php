<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use App\Modules\Logistique\Enums\NatureMateriel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategorieMateriel extends Model
{
    protected $table = 'categories_materiel';

    protected $fillable = [
        'code', 'nom', 'nature', 'actif', 'ordre',
    ];

    protected $casts = [
        'nature' => NatureMateriel::class,
        'actif' => 'boolean',
        'ordre' => 'integer',
    ];

    public function equipements(): HasMany
    {
        return $this->hasMany(Equipement::class, 'categorie_id');
    }
}
