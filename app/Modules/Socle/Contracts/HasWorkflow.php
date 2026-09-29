<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

interface HasWorkflow
{
    /** Retourne la relation Eloquent vers la table d'historique des statuts */
    public function historiqueStatuts(): HasMany;

    /** Retourne le tableau des transitions de statut autorisées */
    public function transitionsAutorisees(): array;
}
