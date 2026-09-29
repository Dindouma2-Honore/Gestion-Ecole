<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface BibliothequeServiceInterface
{
    /** @throws ExemplaireIndisponibleException */
    public function emprunter(int $exemplaireId, Model $emprunteur, int $dureeJours = 14): object;

    /** Calcule et applique automatiquement la pénalité si retard */
    public function retourner(int $empruntId, string $etatRetour = 'bon'): object;

    public function reserver(int $livreId, Model $emprunteur): object;

    public function getEmpruntsEnRetard(): Collection;

    public function getHistoriqueEmprunteur(Model $emprunteur): Collection;
}
