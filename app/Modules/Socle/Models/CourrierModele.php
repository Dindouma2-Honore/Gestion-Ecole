<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;

class CourrierModele extends Model
{
    protected $table = 'courrier_modeles';
    protected $fillable = ['nom', 'objet', 'contenu', 'variables', 'actif'];
    protected $casts = ['variables' => 'array', 'actif' => 'boolean'];
}
