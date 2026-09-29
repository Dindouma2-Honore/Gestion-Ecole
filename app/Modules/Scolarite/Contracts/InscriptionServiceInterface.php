<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Contracts;

interface InscriptionServiceInterface
{
    public function inscrire(int $eleveId, int $classeId, int $anneeScolaireId): int;

    public function reinscrire(int $eleveId, int $classeId, int $anneeScolaireId, array $fraisDiversIds = []): int;

    public function demarrerPreinscription(
        int $eleveId,
        int $classeId,
        int $anneeScolaireId,
        int $parentId,
        array $fraisOptionnels = [],
        ?float $montantVerse = null,
    ): int;

    public function preparerEtDemarrerPreinscription(
        ?int $eleveId,
        array $eleve,
        ?int $parentId,
        array $parent,
        string $lienParente,
        int $classeId,
        int $anneeScolaireId,
        array $fraisOptionnels = [],
        ?float $montantVerse = null,
    ): int;

    /**
     * Crée (si besoin) le dossier élève et le dossier parent, les lie, puis
     * valide l'inscription immédiatement via inscrire() — sans passer par
     * le module Finances. À utiliser tant que la préinscription avec
     * facture provisoire (InscriptionFacturationPort) n'est pas disponible.
     */
    public function preparerEtInscrire(
        ?int $eleveId,
        array $eleve,
        ?int $parentId,
        array $parent,
        string $lienParente,
        int $classeId,
        int $anneeScolaireId,
    ): int;

    public function validerApresPaiement(int $inscriptionId): void;

    public function activerApresVersement(int $inscriptionId): void;

    public function estInscrit(int $eleveId, int $anneeScolaireId): bool;

    /**
     * Utilisé par le module Finances pour savoir combien un élève doit
     * encore payer, sans jamais interroger directement la table
     * inscriptions (voir plan de projet, slide "Un exemple concret").
     */
    public function getFraisRestants(int $inscriptionId): float;

    /** @return array{inscription_id:int, eleve_id:int, classe_id:int, annee_scolaire_id:int, statut:string} */
    public function getContexteFinancier(int $inscriptionId): array;
}
