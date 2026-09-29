<?php

declare(strict_types=1);

namespace App\Modules\Finances\Contracts;

interface GestionDepenseServiceContract
{
    public function creerRubrique(string $nom): object;

    public function creerDepense(int $rubriqueId, string $libelle, float $montant, string $motif, ?string $justificatif = null): object;

    public function valider(int $depenseId, string $motif): object;

    public function marquerPayee(int $depenseId): object;
}
