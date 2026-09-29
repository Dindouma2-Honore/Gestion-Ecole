<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Contracts;

use Illuminate\Support\Collection;

interface SanteServiceContract
{
    public function creerOuMettreAJourDossier(int $eleveId, array $donneesMedicales): object;

    public function consulterDossier(int $eleveId): object;

    public function enregistrerVisite(int $eleveId, string $motif, string $gravite, ?string $soins = null): object;

    public function getInfosUrgence(int $eleveId): object;
}
