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

    /**
     * Primes de la période. Les primes ponctuelles validées portent un `prime_id`
     * afin que la Paie puisse les marquer intégrées (une seule application).
     */
    public function getPrimesPourPeriode(int $personnelId, Carbon $debut, Carbon $fin): Collection;

    /** Marque des primes ponctuelles validées comme intégrées à un bulletin (anti double application). */
    public function marquerPrimesIntegrees(array $primeIds, int $bulletinId): void;
}
