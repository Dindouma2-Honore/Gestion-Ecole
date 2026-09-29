<?php

declare(strict_types=1);

namespace App\Modules\Finances\Contracts;

use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Port de lecture qu'E44 doit fournir à la balance, sans exposer ses Models.
 * Les dépenses payées passent par la Caisse (E41) et n'ont donc plus besoin
 * de ce port — seules les dépenses en attente de validation, qui ne sont pas
 * encore décaissées, ne peuvent pas venir de la Caisse par définition.
 */
interface BalanceDepenseProviderContract
{
    public function getDepensesEnAttenteValidation(DateTimeInterface $dateDebut, DateTimeInterface $dateFin): Collection;
}
