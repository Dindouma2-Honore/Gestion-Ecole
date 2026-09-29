<?php

declare(strict_types=1);

namespace App\Modules\Finances\Contracts;

interface RepartitionPaiementServiceContract
{
    /**
     * @return array{versement_reference:string, montant:float, lignes:array<int, object>}
     */
    public function repartir(int $inscriptionId, float $montantVerse, string $mode, ?string $referenceMobileMoney = null): array;

    /** Paie exclusivement une ligne de frais divers, hors cascade scolaire. */
    public function payerFraisDivers(int $fraisDiversEleveId, float $montantVerse, string $mode, ?string $referenceMobileMoney = null): object;
}
