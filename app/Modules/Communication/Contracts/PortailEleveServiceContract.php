<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

interface PortailEleveServiceContract
{
    public function getVuePersonnelle(int $eleveUserId): object;
}
