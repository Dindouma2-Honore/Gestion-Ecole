<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

/**
 * Surface publique du module Socle pour tout ce qui touche à l'année
 * scolaire active. C'est la SEULE façon dont un autre module a le droit
 * de savoir "quelle est l'année scolaire en cours" — jamais de requête
 * directe sur la table annees_scolaires depuis un autre module.
 */
interface AnneeScolaireServiceInterface
{
    /**
     * Retourne l'identifiant de l'année scolaire active (statut "Active").
     */
    public function anneeActiveId(): int;

    /**
     * Indique si l'année scolaire passée en paramètre est actuellement active.
     */
    public function estActive(int $anneeScolaireId): bool;

    /**
     * Retourne les dates de début/fin de l'année scolaire donnée.
     *
     * @return array{debut: \DateTimeImmutable, fin: \DateTimeImmutable}
     */
    public function periode(int $anneeScolaireId): array;
}
