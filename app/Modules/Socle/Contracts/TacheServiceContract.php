<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface TacheServiceContract
{
    public function creerTache(
        string $titre,
        int $responsableId,
        DateTimeInterface $echeance,
        ?Model $taskable = null,
        string $priorite = 'normale',
        ?string $description = null
    ): object;

    /** Démarre un circuit de validation à plusieurs niveaux pour une tâche */
    public function demarrerCircuitValidation(int $tacheId, array $validateurIds): void;

    /** Le validateur en cours valide ou rejette son étape */
    public function validerEtape(int $tacheId, int $validateurId, bool $approuve, ?string $commentaire = null): void;

    /** Retourne les tâches en retard (échéance dépassée, non clôturées) */
    public function getTachesEnRetard(): Collection;
}
