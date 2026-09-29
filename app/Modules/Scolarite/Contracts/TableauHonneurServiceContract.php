<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Contracts;

use Illuminate\Support\Collection;

interface TableauHonneurServiceContract
{
    /** Composition manuelle par la Direction — jamais de génération automatique par seuil de moyenne. */
    public function composer(int $eleveId, int $periodeId, int $anneeScolaireId, ?string $mention = null): object;

    public function getTableauDeLaPeriode(int $periodeId, int $anneeScolaireId): Collection;

    public function retirer(int $tableauHonneurId, string $motif): void;
}
