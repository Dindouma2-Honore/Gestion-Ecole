<?php

namespace App\Modules\RH\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inspection extends Model
{
    protected $table = 'inspections';

    protected $fillable = [
        'enseignant_id',
        'date_inspection',
        'inspecteur_id',
        'note',
        'observations',
        'recommandations',
    ];

    protected function casts(): array
    {
        return [
            'date_inspection' => 'date',
            'note' => 'decimal:2',
        ];
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class, 'enseignant_id');
    }

    public function inspecteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspecteur_id');
    }
}
