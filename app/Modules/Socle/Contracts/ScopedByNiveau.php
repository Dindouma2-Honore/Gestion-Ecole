<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

/**
 * Contrat des modèles dont les lignes doivent être limitées au niveau
 * scolaire du Directeur ou de l'Enseignant connecté.
 */
interface ScopedByNiveau
{
    /**
     * Colonne directe (`niveau_id`) ou chemin relationnel
     * (`evaluation.classe.niveau_id`) portant le niveau.
     */
    public function getNiveauScopeColumn(): string;
}
