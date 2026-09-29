<?php

namespace App\Modules\RH\Contracts;

use Illuminate\Support\Collection;

interface ContratServiceInterface
{
    public function creerContrat(array $donnees): object;

    public function renouveler(int $contratId, \DateTimeInterface $nouvelleDateFin): object;

    public function resilier(int $contratId, \DateTimeInterface $dateEffet, string $motif): void;

    public function getContratActif(int $employeId): ?object;

    public function getContratsExpirantBientot(int $joursAvant): Collection;

    public function estActif(int $contratId): bool;

    public function getSolde(int $employeId): float;
}
