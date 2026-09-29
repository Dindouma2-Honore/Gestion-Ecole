<?php

namespace App\Modules\RH\Contracts;

use Illuminate\Support\Collection;

interface EnseignantServiceInterface
{
    public function getAffectationsCourantes(int $enseignantId): Collection;

    public function estAffecteA(int $enseignantId, int $matiereId, int $classeId): bool;

    public function enregistrerRemplacement(int $enseignantAbsentId, ?int $remplacantId, \DateTimeInterface $debut, ?\DateTimeInterface $fin, string $motif): object;

    public function getEnseignantsDisponiblesPourRemplacement(int $niveauId, \DateTimeInterface $date): Collection;

    public function existe(int $enseignantId): bool;

    public function getChargeHoraire(int $enseignantId): float;
}
