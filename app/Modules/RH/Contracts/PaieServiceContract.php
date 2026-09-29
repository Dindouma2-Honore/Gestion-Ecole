<?php

namespace App\Modules\RH\Contracts;

interface PaieServiceContract
{
    public function demanderAvance(int $employeId, float $montant, string $motif, bool $derogationPlafond = false, ?string $motifDerogation = null): object;

    public function validerAvance(int $avanceId, string $motifValidation): object;

    /** Retourne le bulletin calculé sans exposer le modèle interne du module RH. */
    public function calculerBulletin(int $employeId, int $mois, int $annee): object;

    public function validerBulletin(int $bulletinId, int $validateurId): void;

    public function marquerPaye(int $bulletinId, \DateTimeInterface $datePaiement): void;

    public function getMasseSalariale(int $mois, int $annee): float;

    /**
     * Somme des bulletins calculés/validés mais pas encore décaissés —
     * obligation future, absente de la Caisse tant qu'elle n'est pas payée ;
     * ne doit jamais être calculé depuis les mouvements de Caisse.
     */
    public function getSalairesDus(int $mois, int $annee): float;

    public function genererEtatVirement(int $mois, int $annee): object;
}
