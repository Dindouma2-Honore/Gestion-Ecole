<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * Module 3 — Années scolaires et périodes.
 *
 * Ce modèle est interne au module Socle : aucun autre module ne doit
 * l'importer directement. Ils passent par AnneeScolaireServiceInterface.
 */
class AnneeScolaire extends Model
{
    protected $table = 'annees_scolaires';

    protected $fillable = [
        'libelle',      // ex: "2026-2027"
        'date_debut',
        'date_fin',
        'statut',       // brouillon | active | cloturee | archivee
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_ACTIVE = 'active';

    public const STATUT_CLOTUREE = 'cloturee';

    public const STATUT_ARCHIVEE = 'archivee';

    public function periodes(): HasMany
    {
        return $this->hasMany(Periode::class)->orderBy('ordre');
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            Cache::forget('annee_scolaire_courante');
            Cache::forget('annee_scolaire_courante_id_v2');
        });
        static::deleted(function (): void {
            Cache::forget('annee_scolaire_courante');
            Cache::forget('annee_scolaire_courante_id_v2');
        });
    }
}
