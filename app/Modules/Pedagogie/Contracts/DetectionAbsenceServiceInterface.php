<?php

namespace App\Modules\Pedagogie\Contracts;

use Illuminate\Support\Collection;

interface DetectionAbsenceServiceInterface
{
    /** Exécuté par le Job planifié — scanne les séances en cours sans appel fait */
    public function detecterAnomalies(): Collection;

    public function marquerResolue(int $anomalieId): void;

    public function getAnomaliesNonResolues(): Collection;
}
