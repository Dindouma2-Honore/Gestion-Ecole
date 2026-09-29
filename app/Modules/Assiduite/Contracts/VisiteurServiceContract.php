<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface VisiteurServiceContract
{
    public function enregistrerEntree(string $nom, string $motif, ?Model $personneVisitee, ?string $telephone = null): object;

    public function enregistrerSortie(int $visiteurId): void;

    public function getVisiteursPresents(): Collection;

    public function verifierAutorisationRecuperationEleve(string $nomVisiteur, int $eleveId): bool;
}
