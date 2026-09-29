<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Contracts;

interface ParentTuteurServiceInterface
{
    public function existe(int $parentId): bool;

    /** @return array{id: int, nom: string, prenom: string, telephone: ?string} */
    public function getParent(int $parentId): array;

    public function creer(array $donnees): array;

    public function lierAEleve(int $parentId, int $eleveId, array $responsabilites): void;
    /**
 * Retourne les parents/tuteurs autorisés à récupérer physiquement cet
 * élève (colonne autorise_recuperation sur la table pivot eleve_parent).
 * Utilisé par le module Vie scolaire (G.56 — sortie des élèves).
 *
 * @return array<int, array{id: int, nom: string, prenom: string, telephone: ?string}>
 */
public function getPersonnesAutoriseesRecuperer(int $eleveId): array;
}
