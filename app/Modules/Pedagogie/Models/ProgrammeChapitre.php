<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgrammeChapitre extends Model
{
    use HasFactory;

    protected $table = 'programme_chapitres';

    protected $fillable = [
        'programme_id',
        'titre',
        'ordre',
        'objectifs_pedagogiques',
        'periode_prevue_id',
    ];

    protected $casts = [
        'ordre' => 'integer',
        'periode_prevue_id' => 'integer',
    ];

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_id');
    }
}
