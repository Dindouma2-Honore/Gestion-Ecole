<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Providers;

use App\Modules\Assiduite\Contracts\ReclamationServiceContract;
use App\Modules\Assiduite\Contracts\SanteServiceContract;
use App\Modules\Assiduite\Contracts\SortieEleveServiceContract;
use App\Modules\Assiduite\Contracts\VisiteurServiceContract;
use App\Modules\Assiduite\Services\ReclamationService;
use App\Modules\Assiduite\Services\SanteService;
use App\Modules\Assiduite\Services\SortieEleveService;
use App\Modules\Assiduite\Services\VisiteurService;
use Illuminate\Support\ServiceProvider;

class AssiduiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VisiteurServiceContract::class, VisiteurService::class);
        $this->app->bind(SortieEleveServiceContract::class, SortieEleveService::class);
        $this->app->bind(SanteServiceContract::class, SanteService::class);
        $this->app->bind(ReclamationServiceContract::class, ReclamationService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
