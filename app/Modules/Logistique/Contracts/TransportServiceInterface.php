<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Contracts;
use Illuminate\Support\Collection;

interface TransportServiceInterface
{
    /** @throws CapaciteVehiculeDepasseeException */
    public function inscrireEleve(int $eleveId, int $circuitId, int $arretId): object;

    public function enregistrerPresenceTrajet(int $inscriptionId, string $trajet, bool $present): void;

    public function signalerIncident(int $circuitId, string $description, string $gravite): object;

    public function getListeEleveParCircuit(int $circuitId): Collection;

    public function getTauxOccupation(int $circuitId): float;
}
