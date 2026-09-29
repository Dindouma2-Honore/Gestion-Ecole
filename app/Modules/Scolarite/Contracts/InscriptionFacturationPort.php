<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Contracts;

/** Port fourni par Finances pour facturer une préinscription. */
interface InscriptionFacturationPort
{
    /** @return array{obligatoire: array{code:string,label:string,montant:float}|null, optionnels: array<string, array{label:string,montant:float}>, total_scolarite:float} */
    public function getFraisDisponibles(int $niveauId, int $anneeScolaireId, ?int $classeId = null): array;

    /**
     * Retourne une explication exploitable par l'interface lorsque la
     * facturation ne peut pas être démarrée, ou null si elle est prête.
     */
    public function erreurConfiguration(
        int $niveauId,
        int $anneeScolaireId,
        array $fraisOptionnels = [],
    ): ?string;

    public function creerFactureProvisoire(
        int $inscriptionId,
        int $eleveId,
        int $niveauId,
        int $classeId,
        int $anneeScolaireId,
        int $parentId,
        array $fraisOptionnels,
        ?float $montantVerse,
        array $identite,
    ): object;

    /** Retourne les données d'affichage de la facture sans exposer le modèle Finances. */
    public function getFactureProvisoire(int $inscriptionId): ?object;

    public function getResteAPayer(int $inscriptionId): float;

    /** @return array<int, array<string, mixed>> */
    public function getVersements(int $inscriptionId): array;

    /** @return array{nom:string, contenu:string}|null */
    public function getRecuDernierVersement(int $inscriptionId): ?array;
}
