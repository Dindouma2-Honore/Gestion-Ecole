<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

interface PortailParentServiceContract
{
    /** Vue agrégée complète pour un enfant donné */
    public function getVueEnfant(int $parentId, int $eleveId): object;

    /** Vérifie que ce parent a le droit de voir cet enfant */
    public function parentAutoriseVoirEnfant(int $parentId, int $eleveId): bool;
}
