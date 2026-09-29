<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Exceptions;

use Exception;

/**
 * Levée AVANT toute écriture en base lorsque le montant saisi dépasse le
 * reste à payer global de l'inscription — rejet intégral, jamais
 * d'acceptation partielle ni de crédit créé (Module 5 §1).
 */
class MontantVersementExcedentaireException extends Exception
{
    public function __construct(
        public readonly float $montantSaisi,
        public readonly float $resteAPayer,
    ) {
        parent::__construct(
            "Le montant saisi ({$montantSaisi}) dépasse le reste à payer de l'inscription ({$resteAPayer}). ".
            'Aucune écriture effectuée — corrigez le montant avant de valider.'
        );
    }
}
