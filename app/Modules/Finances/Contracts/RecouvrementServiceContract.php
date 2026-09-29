<?php

declare(strict_types=1);

namespace App\Modules\Finances\Contracts;

use DateTimeInterface;
use Illuminate\Support\Collection;

interface RecouvrementServiceContract
{
    /** Exécuté par le Job planifié — scanne tous les élèves avec un reste à payer. */
    public function traiterRelancesImpayes(): void;

    public function proposerEcheancierNegocie(int $eleveId, float $montantTotal, int $nombreTranches, DateTimeInterface $datePremiereTranche): object;

    public function enregistrerPromesse(int $eleveId, DateTimeInterface $datePromesse, float $montant): object;

    public function getTauxRecouvrement(int $anneeScolaireId): float;

    /**
     * Retourne les élèves ayant un reste à payer, sous la forme
     * {eleve_id, nom, prenom, reste_a_payer} — jamais un modèle Eloquent
     * du module Scolarité.
     */
    public function getListeDebiteurs(?int $niveauId = null): Collection;
}
