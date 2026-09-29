<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * `niveau_id` et `annee_scolaire_id` référencent des tables du module Socle
 * mais n'ont volontairement PAS de relation Eloquent `belongsTo` vers
 * `Socle\Models\*` : ce module ne connaît le Socle qu'à travers ses
 * Contracts (ParametrageServiceContract, AnneeScolaireServiceContract).
 */
class GrilleFrais extends Model
{
    protected $table = 'grilles_frais';

    protected $guarded = [];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Compatibilité de transition pour les intégrations E.40/E.42 encore
        // basées sur l'ancien code texte : la colonne ENUM a disparu, mais
        // le modèle matérialise immédiatement le nouveau référentiel.
        static::creating(function (GrilleFrais $grille): void {
            $ancienType = $grille->getAttributes()['type_frais'] ?? null;
            if (! $ancienType || $grille->type_frais_recurrent_id) {
                return;
            }

            $groupeCode = in_array($ancienType, ['inscription', 'scolarite'], true) ? 'scolarite' : 'autres';
            $groupeId = GroupeFrais::query()->where('code', $groupeCode)->value('id');
            $type = TypeFraisRecurrent::query()->firstOrCreate(
                [
                    'nom' => Str::headline((string) $ancienType),
                    'niveau_id' => $grille->niveau_id,
                    'annee_scolaire_id' => $grille->annee_scolaire_id,
                ],
                [
                    'groupe_frais_id' => $groupeId,
                    'montant' => $grille->montant,
                    'actif' => true,
                ],
            );
            $grille->type_frais_recurrent_id = $type->id;
            unset($grille->attributes['type_frais']);
        });

        static::saved(function (GrilleFrais $grille): void {
            if (! $grille->type_frais_recurrent_id) {
                return;
            }

            TypeFraisRecurrent::query()
                ->whereKey($grille->type_frais_recurrent_id)
                ->update([
                    'niveau_id' => $grille->niveau_id,
                    'annee_scolaire_id' => $grille->annee_scolaire_id,
                    'montant' => $grille->montant,
                ]);
        });
    }

    public function getTypeFraisAttribute(): ?string
    {
        return $this->typeRecurrent ? Str::slug($this->typeRecurrent->nom) : null;
    }

    public function echeancier(): HasMany
    {
        return $this->hasMany(EcheancierPaiement::class)->orderBy('ordre');
    }

    public function typeRecurrent(): BelongsTo
    {
        return $this->belongsTo(TypeFraisRecurrent::class, 'type_frais_recurrent_id');
    }
}
