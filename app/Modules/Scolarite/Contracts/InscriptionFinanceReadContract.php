<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Contracts;

interface InscriptionFinanceReadContract
{
    /**
     * @return array<int, string>
     */
    public function getElevesInscritsOptions(int $anneeScolaireId): array;

    /**
     * Options d'inscriptions existantes pour un versement, indexées par ID
     * d'inscription (jamais par ID élève).
     *
     * @return array<int, string>
     */
    public function getInscriptionsOptions(int $anneeScolaireId): array;

    /**
     * Projection métier d'une inscription sans exposer de modèle Scolarité.
     *
     * @return array{id:int,classe_id:int,eleve_id:int,annee_scolaire_id:int,statut:string,classe:object|null,anneeScolaire:object|null,eleve:object|null,parent_principal_nom:?string,parent_principal_telephone:?string}|null
     */
    public function getInfoInscriptionParId(int $inscriptionId): ?array;

    /**
     * Projections des inscriptions valides d'une année pour les listes Finance.
     *
     * @param  array<int, string>  $statuts
     * @return array<int, array{id:int,classe_id:int,classe_nom:string,eleve_id:int,eleve_nom:string,eleve_prenom:string,matricule_permanent:?string,annee_scolaire_id:int,statut:string}>
     */
    public function getInscriptionsPourAnnee(int $anneeScolaireId, array $statuts = ['active', 'validee']): array;

    /** @return array{id:int,nom:string}|null */
    public function getInfoClasse(int $classeId): ?array;

    /** Validation de l'inscription après confirmation d'un paiement Finance. */
    public function validerApresPaiement(int $inscriptionId): void;

    /**
     * @return array{
     *     id: int,
     *     classe_id: int,
     *     eleve_id: int,
     *     annee_scolaire_id: int,
     *     classe: object|null,
     *     anneeScolaire: object|null,
     *     eleve: object|null,
     *     parent_principal_nom: ?string,
     *     parent_principal_telephone: ?string
     * }|null
     */
    public function getInfoInscription(?int $inscriptionId, int $eleveId, int $anneeScolaireId): ?array;
}
