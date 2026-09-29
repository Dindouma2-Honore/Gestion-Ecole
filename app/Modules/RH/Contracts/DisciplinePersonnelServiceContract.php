<?php

namespace App\Modules\RH\Contracts;

use Illuminate\Support\Collection;

interface DisciplinePersonnelServiceContract
{
    public function enregistrerSanction(int $employeId, string $type, string $motif, ?int $dureeJours = null): object;

    public function valider(int $sanctionId, int $validateurId): void;

    public function annuler(int $sanctionId, string $motifAnnulation): void;

    public function estSuspenduA(int $employeId, \DateTimeInterface $date): bool;

    public function getHistoriqueSanctions(int $employeId): Collection;
}
