<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

interface HabilitationServiceContract
{
    public function moduleActifPourRole(string $role, string $moduleCode): bool;

    public function moduleActifPourUser(User $user, string $moduleCode): bool;

    public function accesAccordeParGroupe(User $user, string $fonctionnaliteCode): bool;

    public function activerModulePourRole(string $role, string $moduleCode): void;

    public function desactiverModulePourRole(string $role, string $moduleCode, string $motif): void;

    public function accorderModulePourUser(User $user, string $moduleCode): void;

    public function retirerModulePourUser(User $user, string $moduleCode, string $motif): void;

    public function getMatricePourUser(User $user): Collection;

    public function getMatriceComplete(): Collection;

    public function getMatricePourRole(string $role): Collection;

    public function getModulesActifsPourUser(User $user): Collection;

    public function getCatalogueParCategorie(): Collection;

    public function synchroniserCatalogue(array $fonctionnalites): void;
}
