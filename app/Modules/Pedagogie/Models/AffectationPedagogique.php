<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AffectationPedagogique extends Model
{
    public const TYPE_PRINCIPAL = 'principal';
    public const TYPE_ASSISTANT = 'assistant';
    public const TYPE_MATIERE = 'matiere';

    protected $table = 'affectations_pedagogiques';

    protected $guarded = [];

    protected $casts = ['actif' => 'boolean', 'coefficient' => 'float', 'date_debut' => 'date', 'date_fin' => 'date'];

    public function matieresSpecialisees(): BelongsToMany
    {
        return $this->belongsToMany(Matiere::class, 'affectation_pedagogique_matieres');
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function offre(): BelongsTo
    {
        return $this->belongsTo(OffrePedagogique::class, 'offre_pedagogique_id');
    }

    public function isPrincipal(): bool
    {
        return $this->matiere_id === null && $this->offre_pedagogique_id === null;
    }

    public function isAssistant(): bool
    {
        // The current affectation table has no role column, so assistants
        // cannot be distinguished from other class-level assignments here.
        return false;
    }

    public function isMatiere(): bool
    {
        return $this->matiere_id !== null || $this->offre_pedagogique_id !== null;
    }

    public function scopePrincipal(Builder $query): Builder
    {
        return $query->whereNull('matiere_id')->whereNull('offre_pedagogique_id');
    }

    public function scopeAssistants(Builder $query): Builder
    {
        return $query->whereRaw('1 = 0');
    }

    public function scopeMatieres(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $builder->whereNotNull('matiere_id')->orWhereNotNull('offre_pedagogique_id');
        });
    }

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('actif', true);
    }
}
