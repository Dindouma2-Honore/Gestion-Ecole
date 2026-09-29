<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowInstance extends Model
{
    protected $fillable = ['workflow_definition_id', 'workflow_definition_version', 'module_source', 'entite_type', 'entite_id', 'statut', 'etape_courante_id', 'definition_snapshot', 'contexte'];

    protected $casts = ['workflow_definition_version' => 'integer', 'definition_snapshot' => 'array', 'contexte' => 'array'];

    public function etapeCourante(): BelongsTo
    {
        return $this->belongsTo(WorkflowEtape::class, 'etape_courante_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowInstanceTransition::class);
    }
}
