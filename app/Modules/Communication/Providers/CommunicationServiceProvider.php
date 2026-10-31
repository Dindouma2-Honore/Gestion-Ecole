<?php

declare(strict_types=1);

namespace App\Modules\Communication\Providers;

use App\Modules\Communication\Console\Commands\TraiterFileAttenteNotificationsCommand;
use App\Modules\Communication\Console\Commands\WhatsAppTemplatesCommand;
use App\Modules\Communication\Console\Commands\WhatsAppTestCommand;
use App\Modules\Communication\Contracts\AnnonceServiceContract;
use App\Modules\Communication\Contracts\CommunicationParentServiceContract;
use App\Modules\Communication\Contracts\CourrierNotificationServiceContract;
use App\Modules\Communication\Contracts\NotificationServiceContract;
use App\Modules\Communication\Contracts\PortailEleveServiceContract;
use App\Modules\Communication\Contracts\PortailParentServiceContract;
use App\Modules\Communication\Contracts\RendezVousServiceContract;
use App\Modules\Communication\Listeners\SendFeatureAccessChangedNotification;
use App\Modules\Communication\Listeners\SendStaffAccountWhatsApp;
use App\Modules\Communication\Models\CommunicationTemplate;
use App\Modules\Communication\Policies\CommunicationTemplatePolicy;
use App\Modules\Communication\Services\AnnonceService;
use App\Modules\Communication\Services\CommunicationParentService;
use App\Modules\Communication\Services\CourrierNotificationService;
use App\Modules\Communication\Services\EnrollmentWhatsAppListener;
use App\Modules\Communication\Services\NotificationService;
use App\Modules\Communication\Services\PortailEleveService;
use App\Modules\Communication\Services\PortailParentService;
use App\Modules\Communication\Services\RendezVousService;
use App\Modules\Communication\Services\ReportCardWhatsAppListener;
use App\Modules\Pedagogie\Contracts\ReportCardPublished;
use App\Modules\RH\Events\CandidateApplicationAccepted;
use App\Modules\RH\Events\CandidateApplicationRejected;
use App\Modules\RH\Listeners\CandidateApplicationWhatsAppListener;
use App\Modules\Scolarite\Contracts\EnrollmentValidated;
use App\Modules\Socle\Contracts\Events\UserAccountCreated;
use App\Modules\Socle\Contracts\Events\UserAccountReactivated;
use App\Modules\Socle\Contracts\Events\UserFeatureAccessChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(CommunicationTemplate::class, CommunicationTemplatePolicy::class);
        Event::listen(EnrollmentValidated::class, EnrollmentWhatsAppListener::class);
        Event::listen(UserAccountCreated::class, SendStaffAccountWhatsApp::class);
        Event::listen(UserAccountReactivated::class, SendStaffAccountWhatsApp::class);
        Event::listen(UserFeatureAccessChanged::class, SendFeatureAccessChangedNotification::class);
        Event::listen(ReportCardPublished::class, ReportCardWhatsAppListener::class);
        Event::listen(CandidateApplicationAccepted::class, CandidateApplicationWhatsAppListener::class);
        Event::listen(CandidateApplicationRejected::class, CandidateApplicationWhatsAppListener::class);
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'communication');
        $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                TraiterFileAttenteNotificationsCommand::class,
                WhatsAppTestCommand::class,
                WhatsAppTemplatesCommand::class,
            ]);
        }
    }
}
