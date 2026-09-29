<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Livre extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'titre', 'auteur', 'isbn', 'categorie', 'niveau_id',
    ];

    public function exemplaires(): HasMany
    {
        return $this->hasMany(ExemplaireLivre::class);
    }
}
