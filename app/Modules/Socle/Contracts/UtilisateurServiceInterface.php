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

    /**
     * Crée un compte utilisateur et retourne son identifiant.
     *
     * @param array{
     *     nom?: string,
     *     prenom?: string,
     *     email: string,
     *     name?: string,
     *     telephone?: string|null,
     *     password?: string|null,
     *     niveau_id?: int|null,
     *     statut?: string,
     *     must_change_password?: bool
     * } $donnees
     */
    public function creerCompte(array $donnees): int;

    /**
     * Réactive un compte utilisateur existant et met à jour optionnellement son e-mail.
     */
    public function reactiverCompte(int $utilisateurId, ?string $email = null): void;

    /**
     * Met à jour les informations d'identité partagée et de statut d'un compte utilisateur.
     *
     * @param array{
     *     nom?: string,
     *     prenom?: string,
     *     name?: string,
     *     email?: string,
     *     telephone?: string|null,
     *     niveau_id?: int|null,
     *     statut?: string
     * } $donnees
     */
    public function mettreAJourIdentite(int $utilisateurId, array $donnees): void;

    /**
     * Synchronise (remplace) les rôles applicatifs attribués à un utilisateur.
     *
     * @param  string|int|array<string|int>  $roles  Nom(s) ou ID(s) du rôle applicatif à appliquer
     */
    public function synchroniserRoles(int $utilisateurId, string|int|array $roles): void;
}
