<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Contracts;

interface EleveServiceInterface
{
    public function existe(int $eleveId): bool;

    /**
     * @return array{id: int, nom: string, prenom: string, classe_id: int}
     */
    public function getEleve(int $eleveId): array;

    /**
     * Retourne l'identifiant du niveau d'enseignement de l'élève — c'est
     * la clé utilisée pour sélectionner la bonne grille de frais (voir
     * FraisServiceContract::getTarifClasse()).
     */
    public function getNiveauId(int $eleveId): int;

    /**
     * Retourne les identifiants de tous les élèves actuellement inscrits,
     * éventuellement filtrés par niveau — jamais par une requête directe
     * sur la table `eleves` depuis un autre module.
     *
     * @return array<int>
     */
    public function getElevesActifsIds(?int $niveauId = null): array;

    public function creer(array $donnees): array;

    /**
     * Génère un matricule permanent unique. Appelé une seule fois, à la
     * création du dossier élève (voir Eleve::booted()) — jamais à
     * l'inscription, et jamais régénéré ensuite.
     */
    public function genererMatricule(): string;

    /**
     * Données d'identité destinées au tableau financier, sans exposer les modèles Scolarité.
     *
     * @return array<int, array{id:int, nom:string, prenom:string, sexe:?string, classe_id:int, classe:string, niveau_id:int}>
     */
    public function getElevesPourSituationFinanciere(?int $niveauId = null, ?int $classeId = null, ?string $sexe = null): array;

    /**
     * Retourne les élèves actifs d'une classe précise — utilisé pour peupler
     * les sélecteurs (Évaluation, etc.).
     *
     * @return array<int, array{id: int, nom: string, prenom: string}>
     */
    public function getElevesParClasse(int $classeId): array;
}
