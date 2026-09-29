<?php

declare(strict_types=1);

namespace App\Modules\Socle\Providers;

use App\Models\User;
use App\Modules\Socle\Console\Commands\AlerterDocumentsExpirants;
use App\Modules\Socle\Console\Commands\RelancerTachesEnRetard;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\AnneeScolaireServiceInterface;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\CourrierServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Contracts\DocumentTemplateServiceContract;
use App\Modules\Socle\Contracts\GroupeServiceContract;
use App\Modules\Socle\Contracts\HabilitationServiceContract;
use App\Modules\Socle\Contracts\NumerotationServiceContract;
use App\Modules\Socle\Contracts\Observers\VerrouAnneeObserver;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use App\Modules\Socle\Contracts\ReunionServiceContract;
use App\Modules\Socle\Contracts\TacheServiceContract;
use App\Modules\Socle\Contracts\TransfertElevesAnneeContract;
use App\Modules\Socle\Contracts\UserAuthorizationServiceContract;
use App\Modules\Socle\Contracts\UtilisateurServiceInterface;
use App\Modules\Socle\Contracts\WorkflowServiceContract;
use App\Modules\Socle\Http\Controllers\DownloadDocumentController;
use App\Modules\Socle\Http\Controllers\PeriodesScolairesController;
use App\Modules\Socle\Http\Middleware\EnsureUserIsActive;
use App\Modules\Socle\Listeners\UpdateDerniereConnexion;
use App\Modules\Socle\Models\ActivityLog;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\ConfigEtablissement;
use App\Modules\Socle\Models\Document;
use App\Modules\Socle\Models\FormatNumerotation;
use App\Modules\Socle\Models\JourFerie;
use App\Modules\Socle\Models\Niveau;
use App\Modules\Socle\Models\Periode;
use App\Modules\Socle\Models\SeuilValidation;
use App\Modules\Socle\Models\TemplateDocument;
use App\Modules\Socle\Models\TemplateNotification;
use App\Modules\Socle\Policies\AnneeScolairePolicy;
use App\Modules\Socle\Policies\AuditLogPolicy;
use App\Modules\Socle\Policies\DocumentPolicy;
use App\Modules\Socle\Policies\ParametragePolicy;
use App\Modules\Socle\Policies\UserPolicy;
use App\Modules\Socle\Services\AnneeScolaireService;
use App\Modules\Socle\Services\AuditService;
use App\Modules\Socle\Services\CourrierService;
use App\Modules\Socle\Services\DocumentService;
use App\Modules\Socle\Services\DocumentTemplateService;
use App\Modules\Socle\Services\GroupeService;
use App\Modules\Socle\Services\HabilitationService;
use App\Modules\Socle\Services\NumerotationService;
use App\Modules\Socle\Services\ParametrageService;
use App\Modules\Socle\Services\ReunionService;
use App\Modules\Socle\Services\TacheService;
use App\Modules\Socle\Services\TransfertElevesIndisponible;
use App\Modules\Socle\Services\UserAuthorizationService;
use App\Modules\Socle\Services\UtilisateurService;
use App\Modules\Socle\Services\WorkflowService;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SocleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UtilisateurServiceInterface::class, UtilisateurService::class);
        $this->app->bind(UserAuthorizationServiceContract::class, UserAuthorizationService::class);
        $this->app->bind(ParametrageServiceContract::class, ParametrageService::class);
        $this->app->bind(AnneeScolaireServiceInterface::class, AnneeScolaireService::class);
        $this->app->bind(AnneeScolaireServiceContract::class, AnneeScolaireService::class);
        $this->app->bind(AuditServiceContract::class, AuditService::class);
        $this->app->bind(DocumentServiceContract::class, DocumentService::class);
        $this->app->bind(DocumentTemplateServiceContract::class, DocumentTemplateService::class);
        $this->app->bind(WorkflowServiceContract::class, WorkflowService::class);
        $this->app->bind(NumerotationServiceContract::class, NumerotationService::class);
        $this->app->singleton(HabilitationServiceContract::class, HabilitationService::class);
        $this->app->singleton(GroupeServiceContract::class, GroupeService::class);
        $this->app->bind(CourrierServiceContract::class, CourrierService::class);
        $this->app->bind(TacheServiceContract::class, TacheService::class);
        $this->app->bind(ReunionServiceContract::class, ReunionService::class);
        $this->app->bind(TransfertElevesAnneeContract::class, TransfertElevesIndisponible::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        Gate::policy(User::class, UserPolicy::class);
        foreach ([
            ConfigEtablissement::class,
            Niveau::class,
            FormatNumerotation::class,
            TemplateDocument::class,
            TemplateNotification::class,
            JourFerie::class,
            SeuilValidation::class,
            AnneeScolaire::class,
            Periode::class,
        ] as $model) {
            Gate::policy($model, ParametragePolicy::class);
        }
        Gate::policy(AnneeScolaire::class, AnneeScolairePolicy::class);
        Gate::policy(ActivityLog::class, AuditLogPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Event::listen(Login::class, UpdateDerniereConnexion::class);
        Periode::observe(VerrouAnneeObserver::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'socle');
        Route::middleware(['web', 'auth', 'signed'])
            ->get('/documents/{documentId}/telecharger', DownloadDocumentController::class)
            ->name('documents.telecharger');
        Route::middleware(['web', 'auth'])
            ->get('/periodes-scolaires', PeriodesScolairesController::class)
            ->name('periodes-scolaires.index');
        FilamentView::registerRenderHook(
            PanelsRenderHook::CONTENT_START,
            fn (): string => view('socle::filament.annee-active-banner')->render(),
        );

        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', EnsureUserIsActive::class);
        $router->pushMiddlewareToGroup('api', EnsureUserIsActive::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                AlerterDocumentsExpirants::class,
                RelancerTachesEnRetard::class,
            ]);
        }
    }
}
