<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Exceptions;

use Exception;

/**
 * Un frais de scolarité (utilise_grille_tarifaire = true) sans entrée dans
 * grille_tarifaire pour la classe/année concernée ne doit jamais être
 * appliqué avec un montant à 0 par défaut — voir Module 5 §7.
 */
class TarifClasseNonDefiniException extends Exception
{
    public function __construct(int $fraisId, int $classeId, int $anneeScolaireId)
    {
        parent::__construct(
            "Aucun tarif n'est défini dans la grille tarifaire pour le frais #{$fraisId}, ".
            "la classe #{$classeId} et l'année scolaire #{$anneeScolaireId}."
        );
    }
}
