<?php

namespace App\Modules\RH\Providers;

use App\Modules\RH\Contracts\AbsenceServiceContract;
use App\Modules\RH\Contracts\AvanceSalaireServiceContract;
use App\Modules\RH\Contracts\CongeServiceContract;
use App\Modules\RH\Contracts\ContratServiceInterface;
use App\Modules\RH\Contracts\DisciplinePersonnelServiceContract;
use App\Modules\RH\Contracts\EmployeServiceContract;
use App\Modules\RH\Contracts\EnseignantServiceInterface;
use App\Modules\RH\Contracts\FormationServiceContract;
use App\Modules\RH\Contracts\HeuresTravailleesServiceContract;
use App\Modules\RH\Contracts\PaieServiceContract;
use App\Modules\RH\Contracts\PointageServiceContract;
use App\Modules\RH\Contracts\PrimeServiceContract;
use App\Modules\RH\Models\BulletinPaie;
use App\Modules\RH\Observers\BulletinPaieObserver;
use App\Modules\RH\Services\AbsenceService;
use App\Modules\RH\Services\AvanceSalaireService;
use App\Modules\RH\Services\CongeService;
use App\Modules\RH\Services\ContratService;
use App\Modules\RH\Services\DisciplinePersonnelService;
use App\Modules\RH\Services\EmployeService;
use App\Modules\RH\Services\EnseignantService;
use App\Modules\RH\Services\FormationService;
use App\Modules\RH\Services\HeuresTravailleesServiceStub;
use App\Modules\RH\Services\PaieService;
use App\Modules\RH\Services\PointageService;
use App\Modules\RH\Services\PrimeService;
use Illuminate\Support\ServiceProvider;

class RHServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EmployeServiceContract::class, EmployeService::class);
        $this->app->bind(EnseignantServiceInterface::class, EnseignantService::class);
        $this->app->bind(ContratServiceInterface::class, ContratService::class);
        $this->app->bind(CongeServiceContract::class, CongeService::class);
        $this->app->bind(DisciplinePersonnelServiceContract::class, DisciplinePersonnelService::class);
        $this->app->bind(FormationServiceContract::class, FormationService::class);
        $this->app->bind(PrimeServiceContract::class, PrimeService::class);
        $this->app->bind(PointageServiceContract::class, PointageService::class);
        $this->app->bind(PaieServiceContract::class, PaieService::class);
        $this->app->bind(AvanceSalaireServiceContract::class, AvanceSalaireService::class);
        $this->app->bind(AbsenceServiceContract::class, AbsenceService::class);
        $this->app->bind(HeuresTravailleesServiceContract::class, HeuresTravailleesServiceStub::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'rh');
        BulletinPaie::observe(BulletinPaieObserver::class);
    }
}
