<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Contracts;

use Illuminate\Support\Collection;

interface PaiementServiceContract
{
    /**
     * Enregistre le versement ET le répartit automatiquement en cascade
     * sur les frais dus, par ordre de priorité (frais.ordre_repartition
     * croissant, puis created_at pour les frais divers ex-aequo).
     *
     * RÈGLE STRICTE : si $montant dépasse le reste à payer global de
     * l'inscription, l'opération est intégralement rejetée — aucune
     * acceptation partielle, aucun surplus/crédit créé.
     *
     * Si, après répartition, le reste à payer global tombe à 0, déclenche
     * automatiquement InscriptionServiceInterface::activerApresVersement().
     *
     * Lève une exception si $montant <= 0, ou si $montant dépasse le reste
     * à payer global de l'inscription.
     */
    public function enregistrerPaiement(int $inscriptionId, float $montant, string $mode, ?string $referenceMobileMoney = null): object;

    /** Lève une exception si $motif est vide. */
    public function annulerPaiement(int $paiementId, string $motif): void;

    public function getTotalPaye(int $inscriptionId): float;

    public function getResteAPayer(int $inscriptionId): float;

    /** Détail du reste à payer FRAIS PAR FRAIS (ex: reste 20 000 sur Tranche 2) */
    public function getResteParFrais(int $inscriptionId): Collection;

    public function getHistoriquePaiements(int $inscriptionId): Collection;
}
