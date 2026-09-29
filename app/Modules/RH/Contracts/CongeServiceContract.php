<?php

namespace App\Modules\RH\Contracts;

interface CongeServiceContract
{
    public function demanderConge(int $employeId, string $type, \DateTimeInterface $debut, \DateTimeInterface $fin, ?string $motif): object;

    public function approuver(int $congeId, int $validateurId, ?string $commentaire = null): void;

    public function rejeter(int $congeId, int $validateurId, string $motif): void;

    public function estEnCongeAutorise(int $employeId, \DateTimeInterface $date): bool;

    public function getSoldeRestant(int $employeId, int $anneeScolaireId): float;
}
