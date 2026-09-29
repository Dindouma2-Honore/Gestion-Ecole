<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use DateTimeInterface;

interface ParametrageServiceContract
{
    /** Retourne l'unique ligne de configuration de l'établissement */
    public function getConfigEtablissement(): object;

    /** Retourne la liste ordonnée des niveaux d'enseignement */
    public function getTousLesNiveaux(): array;

    /** Retourne un niveau, sans exposer son modèle Eloquent hors du module. */
    public function getNiveau(int $id): ?object;

    /** Retourne un paramètre système typé ou la valeur par défaut. */
    public function getParametre(string $cle, mixed $defaut = null): mixed;

    /**
     * Génère le prochain numéro pour un type de document donné,
     * de façon atomique (thread-safe).
     */
    public function genererNumero(string $typeDocument, array $variables = []): string;

    /** Retourne le contenu d'un template de notification avec ses placeholders remplacés */
    public function getTemplateNotification(string $canal, string $code, array $donnees): string;

    /** Retourne le rôle validateur requis pour un montant dans une catégorie de dépense */
    public function getValidateurRequis(int $categorieDepenseId, float $montant): string;

    /** Un jour donné est-il férié pour un niveau donné (ou tous) ? */
    public function estJourFerie(DateTimeInterface $date, ?int $niveauId = null): bool;
}
