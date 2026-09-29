<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowDefinition extends Model
{
    protected $fillable = ['code', 'nom', 'module_proprietaire', 'version', 'actif'];

    protected $casts = ['version' => 'integer', 'actif' => 'boolean'];

    public function etapes(): HasMany
    {
        return $this->hasMany(WorkflowEtape::class)->orderBy('ordre');
    }
}
