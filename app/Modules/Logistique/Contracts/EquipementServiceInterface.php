<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Contracts;

use Illuminate\Support\Collection;

interface EquipementServiceInterface
{
    public function enregistrerEquipement(array $donnees): object;

    public function deplacer(int $equipementId, int $nouvelleSalleId): void;

    /** Peut lever GarantieExpireeException (avertissement, pas blocage) */
    public function declarerPanne(int $equipementId, string $description): object;

    public function mettreAuRebut(int $equipementId, string $motif): void;

    public function getValeurPatrimoine(): float;

    public function getEquipementsSousGarantie(): Collection;
}