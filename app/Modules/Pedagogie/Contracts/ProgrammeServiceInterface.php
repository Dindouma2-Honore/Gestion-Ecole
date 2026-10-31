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
        array $chapitres,
        ?int $offrePedagogiqueId = null,
    ): object;

    /**
     * Importe un document de programme (PDF/Word), l'enregistre et déclenche l'analyse asynchrone.
     */
    public function importerProgrammeDocument(
        int $enseignantId,
        int $offrePedagogiqueId,
        UploadedFile $fichier,
    ): object;

    /**
     * Permet à l'enseignant de valider ou corriger la structure extraite par l'analyse automatique.
     */
    public function validerParEnseignant(int $programmeId, array $elementsModifies = []): object;

    /**
     * Crée une nouvelle version incrémentale (V2, V3...) d'un programme existant sans altérer l'historique.
     */
    public function creerNouvelleVersion(int $programmeId): object;

    public function archiverProgramme(int $programmeId): void;

    public function importerChapitresDepuisExcel(UploadedFile $fichierExcel): array;

    public function validerProgramme(int $programmeId, int $validateurId): void;

    public function rejeterProgramme(int $programmeId, string $motif): void;

    public function getChapitresPrevus(int $matiereId, int $classeId, int $anneeScolaireId, ?int $enseignantId = null): Collection;

    public function ajouterLecon(int $chapitreId, string $titre, ?string $objectifs = null, float $dureeHeures = 1.0, int $ordre = 1): object;

    public function getLeconsChapitre(int $chapitreId): Collection;

    /**
     * Déclenche l'analyse intelligente (IA) d'un programme existant.
     */
    public function lancerAnalyseIntelligente(int $programmeId, ?int $userId = null): object;

    /**
     * Récupère le programme et propose automatiquement le prochain chapitre et la prochaine leçon à traiter.
     */
    public function getProchaineNotionAEffectuer(
        int $matiereId,
        int $classeId,
        int $anneeScolaireId,
        ?int $enseignantId = null
    ): array;
}
