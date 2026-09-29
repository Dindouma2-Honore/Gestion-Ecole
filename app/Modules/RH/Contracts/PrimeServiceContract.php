<?php

namespace App\Modules\RH\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

interface PrimeServiceContract
{
    public function proposerPrime(int $employeId, string $typePrimeCode, float $montant, int $mois, int $annee, ?string $justification = null): object;

    public function valider(int $primeId, int $validateurId): void;

    public function rejeter(int $primeId, string $motif): void;

    public function getPrimesDuMois(int $employeId, int $mois, int $annee): Collection;

    public function getPrimesActives(int $personnelId, Carbon $periode): Collection;
}
