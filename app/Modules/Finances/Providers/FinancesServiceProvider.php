<?php

declare(strict_types=1);

namespace App\Modules\Finances\Providers;

use App\Modules\Finances\Console\Commands\RelancerRecouvrement;
use App\Modules\Finances\Contracts\BalanceDepenseProviderContract;
use App\Modules\Finances\Contracts\BalanceServiceContract;
use App\Modules\Finances\Contracts\BudgetServiceContract;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Contracts\ConfigurationFraisClasseServiceContract;
use App\Modules\Finances\Contracts\DepenseServiceContract;
use App\Modules\Finances\Contracts\FacturePreinscriptionServiceContract;
use App\Modules\Finances\Contracts\FraisScolaireServiceContract;
use App\Modules\Finances\Contracts\GestionDepenseServiceContract;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Contracts\RecouvrementServiceContract;
use App\Modules\Finances\Contracts\RepartitionPaiementServiceContract;
use App\Modules\Finances\Services\BalanceService;
use App\Modules\Finances\Services\BilanJournalierScolariteService;
use App\Modules\Finances\Services\BudgetService;
use App\Modules\Finances\Services\CaisseService;
use App\Modules\Finances\Services\ConfigurationFraisClasseService;
use App\Modules\Finances\Services\DepenseService;
use App\Modules\Finances\Services\FacturePreinscriptionService;
use App\Modules\Finances\Services\FraisScolaireService;
use App\Modules\Finances\Services\PaiementService;
use App\Modules\Finances\Services\RecouvrementService;
use App\Modules\Finances\Services\RepartitionPaiementService;
use App\Modules\Scolarite\Contracts\BilanJournalierScolariteContract;
use App\Modules\Scolarite\Contracts\InscriptionFacturationPort;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\ServiceProvider;
use Throwable;

class FinancesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BalanceServiceContract::class, BalanceService::class);
        $this->app->bind(BilanJournalierScolariteContract::class, BilanJournalierScolariteService::class);
        $this->app->bind(BudgetServiceContract::class, BudgetService::class);
        $this->app->bind(DepenseServiceContract::class, DepenseService::class);
        $this->app->bind(GestionDepenseServiceContract::class, DepenseService::class);
        $this->app->bind(BalanceDepenseProviderContract::class, DepenseService::class);
        $this->app->bind(CaisseServiceContract::class, CaisseService::class);
        $this->app->bind(ConfigurationFraisClasseServiceContract::class, ConfigurationFraisClasseService::class);
        $this->app->bind(FraisScolaireServiceContract::class, FraisScolaireService::class);
        $this->app->bind(FacturePreinscriptionServiceContract::class, FacturePreinscriptionService::class);
        $this->app->bind(InscriptionFacturationPort::class, FacturePreinscriptionService::class);
        $this->app->bind(PaiementServiceContract::class, PaiementService::class);
        $this->app->bind(RecouvrementServiceContract::class, RecouvrementService::class);
        $this->app->bind(RepartitionPaiementServiceContract::class, RepartitionPaiementService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'finances');
        FilamentView::registerRenderHook(
            PanelsRenderHook::CONTENT_END,
            function (): string {
                if (! request()->routeIs('filament.admin.resources.eleves.edit')) {
                    return '';
                }

                $eleveId = (int) request()->route('record');
                try {
                    $paiements = app(PaiementServiceContract::class)->getHistoriquePaiements($eleveId);
                    $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
                    $montantsParGroupe = app(FraisScolaireServiceContract::class)->getMontantDuParGroupe($eleveId, $anneeId);
                } catch (Throwable) {
                    $paiements = collect();
                    $montantsParGroupe = ['scolarite' => 0.0, 'autres' => 0.0];
                }

                return view('finances::filament.historique-paiements-eleve', compact('paiements', 'montantsParGroupe'))->render();
            },
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                RelancerRecouvrement::class,
            ]);
        }
    }
}
