<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Contracts;

use App\Modules\VieScolaire\Exceptions\PersonneNonAutoriseeException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

interface SortieEleveServiceInterface
{
    /** @throws PersonneNonAutoriseeException */
    public function enregistrerSortieNormale(int $eleveId, int $parentId): object;

    /**
     * Sortie exceptionnelle — nécessite un motif, suit le même principe de
     * motif obligatoire + audit déjà vu dans tout le projet pour les
     * opérations sensibles. Le justificatif éventuel est attaché à
     * l'enregistrement de sortie lui-même, pas à l'élève.
     */
    public function enregistrerSortieExceptionnelle(
        int $eleveId,
        string $nomPersonne,
        string $motif,
        ?UploadedFile $justificatif = null
    ): object;

    public function getHistoriqueSorties(int $eleveId): Collection;
}
