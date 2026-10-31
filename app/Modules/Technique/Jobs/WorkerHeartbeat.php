<?php

declare(strict_types=1);

namespace App\Modules\Technique\Jobs;

use App\Modules\Technique\Contracts\MonitoringServiceContract;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class WorkerHeartbeat implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 15;

    public int $tries = 1;

    public int $uniqueFor = 180;

    public function handle(MonitoringServiceContract $monitoring): void
    {
        $monitoring->heartbeat('queue.worker');
    }
}
