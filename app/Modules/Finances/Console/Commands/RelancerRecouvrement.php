<?php

declare(strict_types=1);

namespace App\Modules\Finances\Console\Commands;

use App\Modules\Finances\Contracts\RecouvrementServiceContract;
use Illuminate\Console\Command;

class RelancerRecouvrement extends Command
{
    protected $signature = 'recouvrement:relancer';

    protected $description = "Envoie les relances de paiement aux élèves ayant un reste à payer";

    public function handle(RecouvrementServiceContract $recouvrement): void
    {
        $recouvrement->traiterRelancesImpayes();

        $this->info('Relances de paiement traitées.');
    }
}
