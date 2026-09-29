<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

/** Port d'intégration que le module Scolarité implémentera sans exposer ses modèles. */
interface TransfertElevesAnneeContract
{
    public function transferer(int $anneeSourceId, int $anneeDestinationId): void;
}
