<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire;

use App\Modules\VieScolaire\Contracts\ReclamationServiceInterface;
use App\Modules\VieScolaire\Contracts\SanteServiceInterface;
use App\Modules\VieScolaire\Contracts\SortieEleveServiceInterface;
use App\Modules\VieScolaire\Contracts\VisiteurServiceInterface;
use App\Modules\VieScolaire\Services\ReclamationService;
use App\Modules\VieScolaire\Services\SanteService;
use App\Modules\VieScolaire\Services\SortieEleveService;
use App\Modules\VieScolaire\Services\VisiteurService;
use Illuminate\Support\ServiceProvider;

class VieScolaireServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SortieEleveServiceInterface::class, SortieEleveService::class);
        $this->app->bind(VisiteurServiceInterface::class, VisiteurService::class);
        $this->app->bind(SanteServiceInterface::class, SanteService::class);
        $this->app->bind(ReclamationServiceInterface::class, ReclamationService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
