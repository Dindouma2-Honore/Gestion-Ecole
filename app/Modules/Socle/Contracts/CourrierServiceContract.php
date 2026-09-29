<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use Illuminate\Support\Collection;

interface CourrierServiceContract
{
    public function enregistrerCourrierEntrant(array $donnees): object;

    public function preparerCourrierSortant(array $donnees): object;

    public function envoyerCourrier(int $courrierId): void;

    public function appliquerModele(int $modeleId, array $variables = []): array;

    public function affecterAService(int $courrierId, int $serviceId): void;

    public function getCourriersEnRetard(): Collection;
}
