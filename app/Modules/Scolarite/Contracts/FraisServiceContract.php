<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Contracts;

use Illuminate\Support\Collection;

interface FraisServiceContract
{
    // Catégories — CRUD libre, aucune catégorie n'est imposée par le
    // système (Module 5 §1).
    public function creerCategorie(string $nom): object;

    public function modifierCategorie(int $id, string $nom): void;

    /** Lève une exception si des frais sont encore rattachés à cette catégorie. */
    public function supprimerCategorie(int $id): void;

    public function listerCategories(): Collection;

    // Frais
    public function creerFrais(
        string $nom,
        float $montant,
        int $categorieId,
        bool $utiliseGrilleTarifaire = false,
        int $ordreRepartition = 999,
    ): object;

    public function modifierFrais(int $id, array $donnees): void;

    public function supprimerFrais(int $id): void;

    /** Frais optionnels que le parent peut choisir à l'inscription (hors grille tarifaire). */
    public function getFraisDiversDisponibles(): Collection;

    // Grille tarifaire
    public function definirTarifClasse(int $fraisId, int $classeId, int $anneeScolaireId, float $montant): void;

    /** Lève une exception si aucun tarif n'est défini pour cette classe/année. */
    public function getTarifClasse(int $fraisId, int $classeId, int $anneeScolaireId): float;

    /**
     * Résout et attache à l'inscription les frais dus : tous les frais de
     * scolarité (utilise_grille_tarifaire = true, obligatoires) via la
     * grille de la classe/année, puis les frais divers sélectionnés à leur
     * montant fixe. Le montant est figé dans `frais_eleve` au moment de
     * l'appel — jamais recalculé dynamiquement ensuite.
     *
     * @param  array<int>  $fraisDiversIds
     */
    public function attacherFraisPourInscription(int $inscriptionId, int $classeId, int $anneeScolaireId, array $fraisDiversIds = []): void;

    /**
     * Simule le montant total qui serait dû pour une classe/année et une
     * sélection de frais divers, sans créer d'inscription — utilisé par
     * l'interface pour afficher le montant avant validation.
     *
     * @param  array<int>  $fraisDiversIds
     */
    public function previsualiserMontant(int $classeId, int $anneeScolaireId, array $fraisDiversIds = []): float;

    // Calcul — LA méthode utilisée par InscriptionServiceInterface et,
    // demain, par un portail parent.
    public function getMontantDu(int $inscriptionId): float;

    /** Liste des frais_eleve avec montant, pour une inscription. */
    public function getDetailFrais(int $inscriptionId): Collection;
}
