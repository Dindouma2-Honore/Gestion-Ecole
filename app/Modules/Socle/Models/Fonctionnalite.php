<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fonctionnalite extends Model
{
    protected $table = 'catalogue_fonctionnalites';

    protected $fillable = ['code', 'nom', 'description', 'categorie', 'module_technique', 'ordre', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function habilitations(): HasMany
    {
        return $this->hasMany(HabilitationRoleFonctionnalite::class, 'fonctionnalite_id');
    }
}
