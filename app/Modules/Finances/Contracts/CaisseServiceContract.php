<?php

declare(strict_types=1);

namespace App\Modules\Finances\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CaisseServiceContract
{
    public function ouvrirSession(float $soldeOuverture): object;

    public function garantirSessionOuverte(): object;

    /**
     * Enregistre un mouvement de caisse — appelé automatiquement par
     * PaiementService (E.40, paiement en espèces) et par un futur module
     * de dépenses, jamais saisi manuellement en double par le Comptable.
     */
    public function enregistrerMouvement(string $type, float $montant, ?Model $source = null, ?string $justificatif = null, ?string $rubrique = null, ?string $moduleOrigine = null, ?string $sousModule = null): object;

    public function cloturerSession(float $soldeReel): object;

    public function getSoldeTheoriqueActuel(): float;

    /** @return array{encaissements:array<string,float>,decaissements:array<string,float>,total_encaissements:float,total_decaissements:float,solde_jour:float} */
    public function getBilanParRubrique(int $sessionId): array;
}
