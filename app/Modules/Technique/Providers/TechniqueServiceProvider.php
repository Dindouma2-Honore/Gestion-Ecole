<?php

declare(strict_types=1);

namespace App\Modules\Technique\Providers;

use App\Modules\Technique\Console\MonitorTechnique;
use App\Modules\Technique\Console\BackupDatabase;
use App\Modules\Technique\Contracts\MonitoringServiceContract;
use App\Modules\Technique\Services\MonitoringService;
use Illuminate\Support\ServiceProvider;

class TechniqueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MonitoringServiceContract::class, MonitoringService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'technique');
        $this->commands([MonitorTechnique::class, BackupDatabase::class]);
    }
}
