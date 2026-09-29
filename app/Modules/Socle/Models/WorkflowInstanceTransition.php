<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowInstanceTransition extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['workflow_instance_id', 'etape_id', 'acteur_id', 'decision', 'motif'];
}
