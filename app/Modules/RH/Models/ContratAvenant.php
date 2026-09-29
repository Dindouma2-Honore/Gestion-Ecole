<?php

namespace App\Modules\RH\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContratAvenant extends Model
{
    protected $table = 'contrat_avenants';

    protected $fillable = [
        'contrat_id',
        'description',
        'date_effet',
        'document_id',
    ];

    protected function casts(): array
    {
        return [
            'date_effet' => 'date',
        ];
    }

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class, 'contrat_id');
    }
}
