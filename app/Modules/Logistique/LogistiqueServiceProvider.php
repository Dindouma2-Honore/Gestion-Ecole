<?php

declare(strict_types=1);

namespace App\Modules\Logistique;

use App\Modules\Logistique\Contracts\BibliothequeServiceInterface;
use App\Modules\Logistique\Contracts\CantineServiceInterface;
use App\Modules\Logistique\Contracts\EquipementServiceInterface;
use App\Modules\Logistique\Contracts\EvenementServiceInterface;
use App\Modules\Logistique\Contracts\InfrastructureServiceInterface;
use App\Modules\Logistique\Contracts\MaintenanceServiceInterface;
use App\Modules\Logistique\Contracts\TransportServiceInterface;
use App\Modules\Logistique\Services\BibliothequeService;
use App\Modules\Logistique\Services\CantineService;
use App\Modules\Logistique\Services\EquipementService;
use App\Modules\Logistique\Services\EvenementService;
use App\Modules\Logistique\Services\InfrastructureService;
use App\Modules\Logistique\Services\MaintenanceService;
use App\Modules\Logistique\Services\TransportService;
use Illuminate\Support\ServiceProvider;

class LogistiqueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InfrastructureServiceInterface::class, InfrastructureService::class);
        $this->app->bind(EquipementServiceInterface::class, EquipementService::class);
        $this->app->bind(MaintenanceServiceInterface::class, MaintenanceService::class);
        $this->app->bind(BibliothequeServiceInterface::class, BibliothequeService::class);
        $this->app->bind(CantineServiceInterface::class, CantineService::class);
        $this->app->bind(TransportServiceInterface::class, TransportService::class);
        $this->app->bind(EvenementServiceInterface::class, EvenementService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
