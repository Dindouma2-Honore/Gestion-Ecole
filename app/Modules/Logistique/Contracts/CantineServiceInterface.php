<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Contracts;

interface CantineServiceInterface
{
    public function souscrireAbonnement(int $eleveId, string $type, \DateTimeInterface $dateDebut): object;

    public function enregistrerPresenceRepas(int $abonnementId, \DateTimeInterface $date, bool $present): void;

    /**
     * Vérifie les allergies de l'élève (via SanteServiceInterface, module
     * VieScolaire) AVANT de confirmer l'inscription au menu du jour —
     * sécurité alimentaire.
     */
    public function verifierCompatibiliteMenu(int $eleveId, \DateTimeInterface $date): array;

    public function getEffectifPrevu(\DateTimeInterface $date): int;
}
