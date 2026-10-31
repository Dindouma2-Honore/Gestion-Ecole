<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie;

use App\Modules\Pedagogie\Contracts\AffectationPedagogiqueServiceInterface;
use App\Modules\Pedagogie\Contracts\BulletinServiceInterface;
use App\Modules\Pedagogie\Contracts\DetectionAbsenceServiceInterface;
use App\Modules\Pedagogie\Contracts\DocumentExtractor;
use App\Modules\Pedagogie\Contracts\EmploiDuTempsServiceInterface;
use App\Modules\Pedagogie\Contracts\EvaluationServiceInterface;
use App\Modules\Pedagogie\Contracts\MatiereServiceInterface;
use App\Modules\Pedagogie\Contracts\NoteServiceInterface;
use App\Modules\Pedagogie\Contracts\PresenceServiceInterface;
use App\Modules\Pedagogie\Contracts\ProgramDocumentAnalyzer;
use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Contracts\ProgressionServiceInterface;
use App\Modules\Pedagogie\Contracts\SeanceServiceInterface;
use App\Modules\Pedagogie\Services\AffectationPedagogiqueService;
use App\Modules\Pedagogie\Services\BulletinService;
use App\Modules\Pedagogie\Services\DetectionAbsenceService;
use App\Modules\Pedagogie\Services\EmploiDuTempsService;
use App\Modules\Pedagogie\Services\EvaluationService;
use App\Modules\Pedagogie\Services\MatiereService;
use App\Modules\Pedagogie\Services\NoteService;
use App\Modules\Pedagogie\Services\OrganisationPedagogiqueContext;
use App\Modules\Pedagogie\Services\PresenceService;
use App\Modules\Pedagogie\Services\ProgrammeService;
use App\Modules\Pedagogie\Services\ProgrammeStructureAnalyzer;
use App\Modules\Pedagogie\Services\ProgrammeTextExtractor;
use App\Modules\Pedagogie\Services\ProgressionCalculator;
use App\Modules\Pedagogie\Services\ProgressionMonitoringService;
use App\Modules\Pedagogie\Services\ProgressionService;
use App\Modules\Pedagogie\Services\SeanceService;
use Illuminate\Support\ServiceProvider;

class PedagogieServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ProgressionMonitoringService::class);
        $this->app->bind(DocumentExtractor::class, ProgrammeTextExtractor::class);
        $this->app->bind(ProgramDocumentAnalyzer::class, ProgrammeStructureAnalyzer::class);
        $this->app->scoped(OrganisationPedagogiqueContext::class);
        $this->app->bind(AffectationPedagogiqueServiceInterface::class, AffectationPedagogiqueService::class);
        $this->app->bind(MatiereServiceInterface::class, MatiereService::class);
        $this->app->bind(NoteServiceInterface::class, NoteService::class);
        $this->app->bind(ProgrammeServiceInterface::class, ProgrammeService::class);
        $this->app->bind(ProgressionServiceInterface::class, ProgressionService::class);
        $this->app->bind(EmploiDuTempsServiceInterface::class, EmploiDuTempsService::class);
        $this->app->bind(SeanceServiceInterface::class, SeanceService::class);
        $this->app->bind(PresenceServiceInterface::class, PresenceService::class);
        $this->app->bind(DetectionAbsenceServiceInterface::class, DetectionAbsenceService::class);
        $this->app->bind(EvaluationServiceInterface::class, EvaluationService::class);
        $this->app->bind(BulletinServiceInterface::class, BulletinService::class);
        $this->app->singleton(Services\ProgramDocumentAnalyzer::class);
        $this->app->singleton(ProgressionCalculator::class);
        $this->app->singleton(ProgressionMonitoringService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'pedagogie');
    }
}
