<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

/**
 * Surface publique du module Socle pour tout ce qui touche aux utilisateurs,
 * rôles et permissions. Les autres modules n'accèdent jamais directement
 * au modèle User ou aux tables de permissions — ils passent par ce contrat.
 */
interface UtilisateurServiceInterface
{
    public function existe(int $utilisateurId): bool;

    /**
     * @return string[] liste des noms de rôles de l'utilisateur (ex: ['enseignant'])
     */
    public function roles(int $utilisateurId): array;

    public function possedePermission(int $utilisateurId, string $permission): bool;
}
