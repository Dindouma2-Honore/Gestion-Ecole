<?php

declare(strict_types=1);

namespace App\Modules\Technique\Console;

use App\Modules\Technique\Contracts\MonitoringServiceContract;
use Illuminate\Console\Command;

class MonitorTechnique extends Command
{
    protected $signature = 'technique:monitor';

    protected $description = 'Mesurer la base principale et les stockages locaux';

    public function handle(MonitoringServiceContract $monitoring): int
    {
        $monitoring->collect();
        $this->info('Mesures enregistrées. Consulter leurs états dans le centre technique.');

        return self::SUCCESS;
    }
}
