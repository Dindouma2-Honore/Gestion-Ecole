<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgrammeAnalyse extends Model
{
    use HasFactory;

    protected $table = 'programme_analyses';

    protected $fillable = [
        'programme_id',
        'status',
        'extracted_text',
        'analysis_result',
        'ai_provider',
        'ai_model',
        'error_message',
        'tokens_used',
        'analyzed_at',
    ];

    protected $casts = [
        'analysis_result' => 'array',
        'analyzed_at' => 'datetime',
        'tokens_used' => 'integer',
        'programme_id' => 'integer',
    ];

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_id');
    }

    public function isTermine(): bool
    {
        return $this->status === 'termine';
    }

    public function isEchec(): bool
    {
        return $this->status === 'echec';
    }

    public function isEnCours(): bool
    {
        return $this->status === 'en_cours';
    }
}
