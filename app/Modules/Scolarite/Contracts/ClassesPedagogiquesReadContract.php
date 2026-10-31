<?php

namespace App\Modules\Scolarite\Contracts;

interface ClassesPedagogiquesReadContract
{
    /** Classes visible to the current user, keyed by id, preserving historical classes. */
    /** Lecture interne des seules classes explicitement autorisées par le module appelant. */
    public function pourIds(array $ids, int $anneeId): array;

    public function pourAnnee(int $anneeId): array;
}
