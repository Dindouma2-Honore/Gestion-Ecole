<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

use DateTimeInterface;
use Illuminate\Support\Collection;

interface AnnonceServiceContract
{
    public function publier(string $titre, string $contenu, string $cibleType, ?int $cibleId, ?DateTimeInterface $dateExpiration = null): object;

    public function getAnnoncesActives(string $cibleType, ?int $cibleId = null): Collection;

    public function marquerLue(int $annonceId, int $userId): void;

    public function getTauxLecture(int $annonceId): float;
}
