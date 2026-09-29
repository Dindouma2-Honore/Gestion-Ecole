<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Models;

use App\Modules\Socle\Contracts\RattacheAAnneeScolaire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FraisEleve extends Model implements RattacheAAnneeScolaire
{
    protected $table = 'frais_eleve';

    protected $fillable = ['inscription_id', 'frais_id', 'montant', 'statut'];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function frais(): BelongsTo
    {
        return $this->belongsTo(Frais::class);
    }

    public function repartitions(): HasMany
    {
        return $this->hasMany(PaiementRepartition::class);
    }

    /**
     * Seules les répartitions issues d'un paiement encore `valide` comptent
     * — un paiement annulé (voir PaiementService::annulerPaiement()) ne
     * doit plus couvrir ce frais, même si la ligne de répartition elle-même
     * n'est jamais supprimée physiquement.
     */
    public function montantPaye(): float
    {
        return (float) $this->repartitions()
            ->whereHas('paiement', fn ($q) => $q->where('statut', 'valide'))
            ->sum('montant_alloue');
    }

    public function resteAPayer(): float
    {
        return max(0.0, (float) $this->montant - $this->montantPaye());
    }

    /** Pas de colonne annee_scolaire_id directe — résolue via l'inscription. */
    public function getAnneeScolaireId(): int
    {
        return (int) $this->inscription->annee_scolaire_id;
    }
}
