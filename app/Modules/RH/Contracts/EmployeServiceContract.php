<?php

namespace App\Modules\RH\Contracts;

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

    public function muter(int $employeId, string $nouveauPoste, ?int $nouveauNiveauId, string $commentaire): void;

    public function promouvoir(int $employeId, string $nouveauPoste, string $commentaire): void;

    public function getEmployeParUser(int $userId): ?object;
}
