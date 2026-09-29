<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

interface GroupeServiceContract
{
    public function creer(array $donnees): object;

    public function modifier(object $groupe, array $donnees): object;

    public function ajouterMembres(object $groupe, array $userIds): void;

    /** Synchronise les membres et audite précisément les ajouts et retraits. */
    public function synchroniserMembres(object $groupe, array $userIds): void;

    public function retirerMembre(object $groupe, User $user): void;

    public function accorderModulePourGroupe(object $groupe, string $moduleCode): void;

    public function retirerModulePourGroupe(object $groupe, string $moduleCode, string $motif): void;

    public function getMatricePourGroupe(object $groupe): Collection;
}
