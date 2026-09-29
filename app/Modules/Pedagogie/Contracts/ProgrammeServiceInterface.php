<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Contracts;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

interface ProgrammeServiceInterface
{
    /** @return array<int, array{id: int, titre: string, matiere_id: int, statut: string}> */
    public function getProgrammesParNiveau(int $niveauId, int $anneeScolaireId): array;

    public function creerProgramme(
        int $matiereId,
        int $niveauId,
        int $anneeScolaireId,
        string $titre,
        ?string $description = null
    ): array;

    public function publier(int $programmeId): array;

    public function soumettreProgramme(
        int $enseignantId,
        int $matiereId,
        int $classeId,
        int $anneeScolaireId,
        UploadedFile $fichierSource,
        array $chapitres
    ): object;

    public function importerChapitresDepuisExcel(UploadedFile $fichierExcel): array;

    public function validerProgramme(int $programmeId, int $validateurId): void;

    public function rejeterProgramme(int $programmeId, string $motif): void;

    public function getChapitresPrevus(int $matiereId, int $classeId, int $anneeScolaireId, ?int $enseignantId = null): Collection;
}
