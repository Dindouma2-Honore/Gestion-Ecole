<?php

declare(strict_types=1);

namespace App\Modules\Technique\Contracts;

interface MonitoringServiceContract
{
    public function collect(): void;

    public function heartbeat(string $component): void;

    public function snapshot(): array;
}
