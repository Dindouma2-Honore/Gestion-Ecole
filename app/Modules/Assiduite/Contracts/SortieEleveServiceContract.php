<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Contracts;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

interface SortieEleveServiceContract
{
    /**
     * @throws \Exception
     */
    public function enregistrerSortieNormale(int $eleveId, int $parentId): object;

    public function enregistrerSortieExceptionnelle(int $eleveId, string $nomPersonne, string $motif, ?UploadedFile $justificatif = null): object;

    public function getHistoriqueSorties(int $eleveId): Collection;
}
