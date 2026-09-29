<?php

declare(strict_types=1);

namespace App\Modules\Finances\Contracts;

use Illuminate\Support\Collection;

interface FraisScolaireServiceContract
{
    /**
     * Calcule le montant total dû par un élève pour l'année, après
     * application des remises/exonérations éventuelles — c'est LA méthode
     * que E.40 (Paiements) devra appeler, jamais un calcul manuel.
     */
    public function getMontantDu(int $eleveId, int $anneeScolaireId): float;

    /**
     * Somme, sur tous les élèves actifs, du montant restant à payer pour
     * l'année — argent pas encore reçu, donc absent de la Caisse par
     * définition ; ne doit jamais être calculé depuis les mouvements de
     * Caisse.
     */
    public function getTotalImpayes(int $anneeScolaireId): float;

    /** @return array{scolarite: float, autres: float} */
    public function getMontantDuParGroupe(int $eleveId, int $anneeScolaireId): array;

    /** @return array<string, array{libelle:string, attendu:float, recu:float, restant:float}> */
    public function getSituationParFrais(int $eleveId, int $anneeScolaireId): array;

    /**
     * @return array{inscription:array{du:float,paye:float,reste:float,statut:string},tranche_1:array{du:float,paye:float,reste:float,statut:string},tranche_2:array{du:float,paye:float,reste:float,statut:string}}
     */
    public function getSituationDeuxTranches(int $eleveId, int $anneeScolaireId): array;

    /** Retourne l'échéancier de paiement applicable à l'élève pour l'année. */
    public function getEcheancier(int $eleveId, int $anneeScolaireId): Collection;

    /** Un motif non vide est obligatoire pour accorder une remise. */
    public function accorderRemise(int $eleveId, string $typeFrais, string $type, float $valeur, string $motif): object;

    /** Retourne la grille de frais d'un niveau pour une année scolaire. */
    public function getGrilleFrais(int $niveauId, int $anneeScolaireId): Collection;
}
