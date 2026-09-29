<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowEtape extends Model
{
    protected $fillable = ['workflow_definition_id', 'ordre', 'nom', 'validateur_type', 'validateur_valeur', 'condition'];

    protected $casts = ['ordre' => 'integer', 'condition' => 'array'];
}
