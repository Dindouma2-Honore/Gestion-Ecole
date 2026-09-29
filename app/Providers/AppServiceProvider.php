<?php

namespace App\Providers;

use App\Filament\Auth\LoginResponse;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\User::observe(\App\Observers\UserObserver::class);
        \App\Modules\RH\Models\SanctionPersonnel::observe(\App\Observers\SanctionPersonnelObserver::class);
        \App\Modules\RH\Models\Employe::observe(\App\Observers\EmployeObserver::class);
        \App\Modules\RH\Models\Enseignant::observe(\App\Observers\EnseignantObserver::class);
        \App\Modules\RH\Models\Inspection::observe(\App\Observers\InspectionObserver::class);
        \App\Modules\RH\Models\Pointage::observe(\App\Observers\PointageObserver::class);
        \App\Modules\RH\Models\Prime::observe(\App\Observers\PrimeObserver::class);
        \App\Modules\RH\Models\Conge::observe(\App\Observers\CongeObserver::class);
        \App\Modules\Communication\Models\Annonce::observe(\App\Observers\AnnonceObserver::class);
        \App\Modules\Communication\Models\MessageParent::observe(\App\Observers\MessageParentObserver::class);
        \App\Modules\Communication\Models\RendezVous::observe(\App\Observers\RendezVousObserver::class);
        \App\Modules\Socle\Models\Tache::observe(\App\Observers\TacheObserver::class);
        \App\Modules\Socle\Models\TacheValidation::observe(\App\Observers\TacheValidationObserver::class);
    }
}
