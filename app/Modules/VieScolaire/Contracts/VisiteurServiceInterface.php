<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface VisiteurServiceInterface
{
    public function enregistrerEntree(string $nom, string $motif, ?Model $personneVisitee, ?string $telephone = null): object;

    public function enregistrerSortie(int $visiteurId): void;

    public function getVisiteursPresents(): Collection;

    /**
     * Vérifie si un visiteur souhaitant récupérer un élève est bien
     * autorisé — réutilise le contrat Scolarité déjà exposé pour G.56,
     * pas de duplication de la donnée d'autorisation.
     */
    public function verifierAutorisationRecuperationEleve(string $nomVisiteur, int $eleveId): bool;
}
