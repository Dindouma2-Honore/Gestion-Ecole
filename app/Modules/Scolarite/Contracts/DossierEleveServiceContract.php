<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Contracts;

interface DossierEleveServiceContract
{
    /**
     * Agrège UNIQUEMENT des références vers des documents déjà générés
     * ailleurs (bulletins via le futur module Évaluations, reçus/factures
     * via ce module, tableau d'honneur, certificats). Ne stocke rien de
     * nouveau — pure lecture.
     */
    public function getDossierComplet(int $eleveId): object;
}
