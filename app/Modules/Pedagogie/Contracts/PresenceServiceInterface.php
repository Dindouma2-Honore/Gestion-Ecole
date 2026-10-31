<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Contracts;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

interface PresenceServiceInterface
{
    // Peut lever AppelDejaEffectueException (voir Exceptions/) si l'appel a déjà été fait et $remplacer est faux.
    public function faireAppel(int $seanceId, array $donneesEleves, bool $remplacer = false): void;

    public function appelDejaFait(int $seanceId): bool;

    public function justifierAbsence(int $presenceId, UploadedFile $justificatif): void;

    public function getTauxPresence(int $eleveId, int $periodeId): float;

    public function getAbsencesNonJustifiees(int $eleveId, \DateTimeInterface $depuis): Collection;

    /**
     * Statut de présence d'un élève à une date donnée (présent, absent,
     * retard...) — c'est le point d'entrée documenté pour les autres
     * modules (ex: Qualité pédagogique, Discipline des élèves).
     */
    public function getStatutJour(int $eleveId, \DateTimeInterface $date): ?string;

    /**
     * Point d'entrée générique de pointage, utilisé notamment par le futur
     * module Biométrie une fois branché (empreinte, badge...).
     */
    public function enregistrerPointage(int $eleveId, int $seanceId, string $statut): void;

    /**
     * Verrouille l'appel d'une séance (clôture par la vie scolaire ou la direction).
     */
    public function verrouillerAppel(int $seanceId): void;

    public function estAppelVerrouille(int $seanceId): bool;
}
