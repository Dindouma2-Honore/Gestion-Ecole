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
        'parent_id', 'type',
        'programme_id',
        'titre',
        'type_structure',
        'objectif',
        'contenu',
        'parties',
        'volume_horaire_prevu',
        'ordre',
        'objectifs_pedagogiques',
        'periode_prevue_id',
        'ressources',
        'statut',
    ];

    protected $casts = [
        'ordre' => 'integer',
        'periode_prevue_id' => 'integer',
        'volume_horaire_prevu' => 'float',
        'parties' => 'array',
    ];

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_id');
    }

    public function lecons(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProgrammeLecon::class, 'programme_chapitre_id')->orderBy('ordre', 'asc');
    }
}
