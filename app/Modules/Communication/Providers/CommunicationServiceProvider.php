<?php

declare(strict_types=1);

namespace App\Modules\Communication\Providers;

use App\Modules\Communication\Console\Commands\TraiterFileAttenteNotificationsCommand;
use App\Modules\Communication\Contracts\AnnonceServiceContract;
use App\Modules\Communication\Contracts\CommunicationParentServiceContract;
use App\Modules\Communication\Contracts\CourrierNotificationServiceContract;
use App\Modules\Communication\Contracts\NotificationServiceContract;
use App\Modules\Communication\Contracts\PortailEleveServiceContract;
use App\Modules\Communication\Contracts\PortailParentServiceContract;
use App\Modules\Communication\Contracts\RendezVousServiceContract;
use App\Modules\Communication\Services\AnnonceService;
use App\Modules\Communication\Services\CommunicationParentService;
use App\Modules\Communication\Services\CourrierNotificationService;
use App\Modules\Communication\Services\NotificationService;
use App\Modules\Communication\Services\PortailEleveService;
use App\Modules\Communication\Services\PortailParentService;
use App\Modules\Communication\Services\RendezVousService;
use Illuminate\Support\ServiceProvider;

class CommunicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationServiceContract::class, NotificationService::class);
        $this->app->bind(CommunicationParentServiceContract::class, CommunicationParentService::class);
        $this->app->bind(CourrierNotificationServiceContract::class, CourrierNotificationService::class);
        $this->app->bind(PortailParentServiceContract::class, PortailParentService::class);
        $this->app->bind(PortailEleveServiceContract::class, PortailEleveService::class);
        $this->app->bind(RendezVousServiceContract::class, RendezVousService::class);
        $this->app->bind(AnnonceServiceContract::class, AnnonceService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                TraiterFileAttenteNotificationsCommand::class,
            ]);
        }
    }
}
