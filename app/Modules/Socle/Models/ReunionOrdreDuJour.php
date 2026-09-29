<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReunionOrdreDuJour extends Model
{
    protected $table = 'reunion_ordre_du_jour';

    public $timestamps = false;

    protected $guarded = [];

    public function reunion(): BelongsTo
    {
        return $this->belongsTo(Reunion::class);
    }
}
