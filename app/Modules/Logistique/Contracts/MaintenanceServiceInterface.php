<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Contracts;

use Illuminate\Support\Collection;

interface MaintenanceServiceInterface
{
    /** Appelé par EquipementService::declarerPanne() — point d'entrée pour toute panne signalée */
    public function signalerPanne(int $equipementId, string $description): object;

    public function diagnostiquer(int $demandeId, string $diagnostic, float $coutEstime): void;

    public function terminerReparation(int $demandeId, float $coutReel, ?string $piecesUtilisees = null): void;

    public function planifierMaintenancePreventive(int $equipementId, int $frequenceJours): object;

    public function getMaintenancesDues(): Collection;
}