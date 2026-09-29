<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormationParticipant extends Model
{
    protected $table = 'formation_participants';

    protected $fillable = [
        'formation_id',
        'employe_id',
        'present',
        'evaluation_note',
        'competences_acquises',
        'attestation_document_id',
    ];

    protected function casts(): array
    {
        return [
            'present' => 'boolean',
            'evaluation_note' => 'decimal:2',
        ];
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class, 'formation_id');
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'employe_id');
    }
}
