<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface AuditServiceContract
{
    /**
     * Enregistre manuellement une opération sensible qui nécessite
     * un motif obligatoire (ex: annulation de paiement).
     */
    public function enregistrerAvecMotif(
        Model $sujet,
        string $description,
        string $motif
    ): void;

    /**
     * Enregistre une opération sans motif obligatoire (ex: création/
     * modification d'un enregistrement, ajout d'un membre).
     */
    public function enregistrer(
        Model $sujet,
        string $description
    ): void;

    /**
     * Enregistre une consultation de donnée sensible.
     */
    public function enregistrerConsultation(
        Model $sujet,
        string $contexte
    ): void;

    /** Retourne l'historique complet d'un modèle donné */
    public function getHistorique(Model $sujet): Collection;
}
