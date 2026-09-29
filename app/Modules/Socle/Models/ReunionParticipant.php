<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReunionParticipant extends Model
{
    protected $table = 'reunion_participants';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'present' => 'boolean',
    ];

    public function reunion(): BelongsTo
    {
        return $this->belongsTo(Reunion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
