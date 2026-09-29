<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

use DateTimeInterface;
use Illuminate\Support\Collection;

interface RendezVousServiceContract
{
    public function demander(int $parentId, int $responsableId, string $motif, DateTimeInterface $dateHeureSouhaitee): object;

    public function confirmer(int $rendezVousId, DateTimeInterface $dateHeureConfirmee): void;

    public function annuler(int $rendezVousId, string $motif): void;

    public function enregistrerCompteRendu(int $rendezVousId, string $compteRendu): void;

    public function getRendezVousAVenir(int $responsableId): Collection;
}
