<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Contracts;

use App\Modules\VieScolaire\Exceptions\DossierSanteInexistantException;

interface SanteServiceInterface
{
    public function creerOuMettreAJourDossier(int $eleveId, array $donneesMedicales): object;

    /**
     * Accès en LECTURE au dossier — DOIT systématiquement enregistrer une
     * consultation via l'audit (Socle), même pour un simple affichage.
     * Chaque écran, export ou appel API qui affiche un dossier santé
     * complet doit passer par cette méthode, jamais par un accès direct
     * au Model DossierSante.
     *
     * @throws DossierSanteInexistantException
     */
    public function consulterDossier(int $eleveId): object;

    public function enregistrerVisite(int $eleveId, string $motif, string $gravite, ?string $soins = null): object;

    /**
     * Version allégée du dossier (groupe sanguin, allergies, contact
     * urgence) pour affichage rapide sur la fiche élève — volontairement
     * PAS auditée comme consulterDossier(), à confirmer avec Joel.
     */
    public function getInfosUrgence(int $eleveId): object;
}
