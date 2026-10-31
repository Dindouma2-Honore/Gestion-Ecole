<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgrammeLecon extends Model
{
    use HasFactory;

    protected $table = 'programme_lecons';

    protected $fillable = [
        'programme_chapitre_id',
        'titre',
        'type_element',
        'objectifs',
        'ordre',
        'volume_horaire_prevu',
        'statut',
    ];

    protected $casts = [
        'ordre' => 'integer',
        'volume_horaire_prevu' => 'float',
    ];

    public function chapitre(): BelongsTo
    {
        return $this->belongsTo(ProgrammeChapitre::class, 'programme_chapitre_id');
    }

    public function progressions(): HasMany
    {
        return $this->hasMany(Progression::class, 'lecon_id');
    }

    public function scopeAFaire(Builder $query): Builder
    {
        return $query->where('statut', 'a_faire');
    }

    public function scopeEnCours(Builder $query): Builder
    {
        return $query->where('statut', 'en_cours');
    }

    public function scopeTerminees(Builder $query): Builder
    {
        return $query->where('statut', 'terminee');
    }
}
