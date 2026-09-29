<?php

declare(strict_types=1);

namespace App\Modules\Socle\Console\Commands;

use App\Modules\Socle\Contracts\TacheServiceContract;
use App\Modules\Socle\Notifications\TacheEnRetard;
use Illuminate\Console\Command;

class RelancerTachesEnRetard extends Command
{
    protected $signature = 'taches:relancer';

    protected $description = 'Relance les tâches en retard';

    public function handle(TacheServiceContract $tacheService): void
    {
        $taches = $tacheService->getTachesEnRetard();
        $this->info("Trouvé {$taches->count()} tâche(s) en retard.");

        foreach ($taches as $tache) {
            if ($tache->responsable === null) {
                $this->warn("Aucun responsable pour la tâche #{$tache->id}.");

                continue;
            }

            $tache->responsable->notify(new TacheEnRetard($tache));
            $this->line("- Alerte envoyée pour la tâche #{$tache->id}: {$tache->titre}");
        }
    }
}
