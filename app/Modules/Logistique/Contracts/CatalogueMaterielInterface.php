<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Contracts;

/**
 * Catalogue du matériel : référentiel des catégories d'équipements et de
 * leur nature (DURABLE / CONSOMMABLE). La nature appartient au catalogue,
 * jamais à l'équipement lui-même.
 *
 * Aucun Model n'est exposé : uniquement des scalaires.
 */
interface CatalogueMaterielInterface
{
    /**
     * Liste les catégories du catalogue, triées par ordre puis nom.
     *
     * @param  bool  $inclusInactives  inclure aussi les catégories désactivées
     * @return array<int, array{id:int, code:string, nom:string, nature:string, actif:bool, ordre:int}>
     */
    public function lister(bool $inclusInactives = false): array;

    /**
     * Crée une catégorie active et retourne son identifiant.
     *
     * @throws CategorieMaterielChampsInvalidesException
     * @throws NatureMaterielInvalideException
     * @throws CategorieMaterielCodeDejaUtiliseException
     */
    public function creer(string $code, string $nom, string $nature, int $ordre = 0): int;

    /**
     * Met à jour l'identité d'une catégorie (code, nom, nature, ordre).
     *
     * @throws CategorieMaterielInconnueException
     * @throws CategorieMaterielChampsInvalidesException
     * @throws NatureMaterielInvalideException
     * @throws CategorieMaterielCodeDejaUtiliseException
     */
    public function modifier(int $categorieId, string $code, string $nom, string $nature, int $ordre): void;

    /**
     * @throws CategorieMaterielInconnueException
     */
    public function activer(int $categorieId): void;

    /**
     * La catégorie désactivée reste consultable (historique) mais ne peut plus
     * être affectée à un nouvel équipement.
     *
     * @throws CategorieMaterielInconnueException
     */
    public function desactiver(int $categorieId): void;

    /**
     * Suppression physique uniquement si la catégorie n'est utilisée par aucun équipement.
     *
     * @throws CategorieMaterielInconnueException
     * @throws CategorieMaterielUtiliseeException
     */
    public function supprimer(int $categorieId): void;

    /**
     * Nature de la catégorie, quelle que soit son activation.
     *
     * @return string 'durable' | 'consommable'
     *
     * @throws CategorieMaterielInconnueException
     */
    public function nature(int $categorieId): string;

    /**
     * Valide qu'une catégorie peut être affectée à un nouvel équipement :
     * elle doit exister et être active.
     *
     * @throws CategorieMaterielInconnueException
     * @throws CategorieMaterielInactiveException
     */
    public function validerAffectation(int $categorieId): void;
}
