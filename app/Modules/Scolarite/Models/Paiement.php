<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Models;

use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Contracts\RattacheAAnneeScolaire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paiement extends Model implements RattacheAAnneeScolaire
{
    protected $table = 'paiements_scolarite';

    protected $fillable = [
        'inscription_id', 'montant', 'mode', 'reference_mobile_money',
        'numero_recu', 'statut', 'encaisse_par', 'document_recu_id',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function annulations(): HasMany
    {
        return $this->hasMany(PaiementAnnulation::class);
    }

    public function repartitions(): HasMany
    {
        return $this->hasMany(PaiementRepartition::class);
    }

    /**
     * `documents` appartient au Socle (Models\*) : deptrac interdit de
     * l'importer directement ici — passage obligé par le Contract.
     */
    public function getUrlDocument(): ?string
    {
        return app(DocumentServiceContract::class)->getUrlTelechargement($this->document_recu_id);
    }

    /**
     * Pas de colonne annee_scolaire_id directe sur `paiements` — résolue via
     * l'inscription rattachée (voir Module 5 §1 : un versement appartient
     * toujours à une inscription, jamais orphelin).
     */
    public function getAnneeScolaireId(): int
    {
        return (int) $this->inscription->annee_scolaire_id;
    }
}
