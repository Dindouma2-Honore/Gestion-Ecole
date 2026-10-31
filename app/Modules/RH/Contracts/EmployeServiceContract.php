<?php

namespace App\Modules\RH\Contracts;

use Illuminate\Support\Collection;

interface EmployeServiceContract
{
    public function creerEmploye(array $donnees): object;

    /**
     * Crée en une seule transaction le dossier RH, le compte d'accès et le contrat.
     *
     * @return array{employe: object, mot_de_passe_temporaire: string}
     */
    public function embaucher(array $donnees): array;

    /** @return array{user: object, mot_de_passe_temporaire: ?string} */
    public function creerAcces(int $employeId, string $email, int $roleId): array;

    public function muter(int $employeId, string|int $nouveauPoste, ?int $nouveauNiveauId, string $commentaire): void;

    public function promouvoir(int $employeId, string|int $nouveauPoste, string $commentaire, ?float $nouveauSalaire = null, ?float $nouveauTauxHoraire = null): void;

    public function getEmployeParUser(int $userId): ?object;

    public function ajouterDiplome(int $employeId, array $donnees): object;

    public function getDiplomes(int $employeId): Collection;
}
