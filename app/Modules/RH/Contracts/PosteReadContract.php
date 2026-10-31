<?php

namespace App\Modules\RH\Contracts;

use Illuminate\Support\Collection;

interface PosteReadContract
{
    public function read(int $posteId): ?array;

    public function readAll(): Collection;

    public function readByDepartement(?string $departement): Collection;

    /** Postes actifs avec parent et occupants résolus, prêts pour l'organigramme. */
    public function organigramme(): Collection;
}
