<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use App\Models\User;

interface AnneeScolaireServiceContract extends AnneeScolaireServiceInterface
{
    /** Active une année scolaire (et clôture l'ancienne) dans une transaction atomique */
    public function activerAnnee(int $anneeScolaireId): void;

    /** Clôture l'année active. */
    public function cloturerAnnee(int $anneeScolaireId): void;

    /** Archive définitivement une année clôturée. */
    public function archiverAnnee(int $anneeScolaireId): void;

    /** Retourne l'année scolaire active courante */
    public function getAnneeCourante(): object;

    /** Retourne une année scolaire par son identifiant, active ou non. */
    public function getAnneeScolaire(int $anneeScolaireId): object;

    /** Retourne toutes les années scolaires, triées de la plus récente à la plus ancienne. */
    public function getToutesLesAnnees(): array;

    /** Retourne l'ID de l'année scolaire active courante */
    public function getAnneeCouranteId(): int;

    /** Vérifie les droits d'écriture sur une année. */
    public function ecritureAutorisee(int $anneeScolaireId, User $user): bool;

    /** Retourne les périodes (trimestres/séquences) d'une année scolaire, triées */
    public function getPeriodes(int $anneeScolaireId): array;

    /** Transfère les inscriptions actives vers la nouvelle année scolaire */
    public function transfererEleves(int $anneeSourceId, int $anneeDestinationId): void;
}
