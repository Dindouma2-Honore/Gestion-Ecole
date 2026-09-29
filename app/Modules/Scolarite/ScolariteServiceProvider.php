<?php

declare(strict_types=1);

namespace App\Modules\Scolarite;

use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Scolarite\Contracts\CourrierDestinataireServiceContract;
use App\Modules\Scolarite\Contracts\DossierEleveServiceContract;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Scolarite\Contracts\FactureServiceInterface;
use App\Modules\Scolarite\Contracts\FraisServiceContract;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Contracts\PaiementServiceContract;
use App\Modules\Scolarite\Contracts\ParentTuteurServiceInterface;
use App\Modules\Scolarite\Contracts\TableauHonneurServiceContract;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\FraisEleve;
use App\Modules\Scolarite\Models\GrilleTarifaire;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\Paiement;
use App\Modules\Scolarite\Models\TableauHonneur;
use App\Modules\Scolarite\Services\ClasseService;
use App\Modules\Scolarite\Services\CourrierDestinataireService;
use App\Modules\Scolarite\Services\DossierEleveService;
use App\Modules\Scolarite\Services\EleveService;
use App\Modules\Scolarite\Services\FactureService;
use App\Modules\Scolarite\Services\FraisService;
use App\Modules\Scolarite\Services\InscriptionService;
use App\Modules\Scolarite\Services\PaiementService;
use App\Modules\Scolarite\Services\ParentTuteurService;
use App\Modules\Scolarite\Services\TableauHonneurService;
use App\Modules\Scolarite\Services\TransfertAnneeService;
use App\Modules\Socle\Contracts\Observers\VerrouAnneeObserver;
use App\Modules\Socle\Contracts\TransfertElevesAnneeContract;
use Illuminate\Support\ServiceProvider;

class ScolariteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EleveServiceInterface::class, EleveService::class);
        $this->app->bind(ParentTuteurServiceInterface::class, ParentTuteurService::class);
        $this->app->bind(ClasseServiceInterface::class, ClasseService::class);
        $this->app->bind(CourrierDestinataireServiceContract::class, CourrierDestinataireService::class);
        $this->app->bind(InscriptionServiceInterface::class, InscriptionService::class);
        $this->app->bind(FactureServiceInterface::class, FactureService::class);

        // Circuit financier interne (Module 5) — l'inscription ne dépend
        // plus d'aucun module Finances externe.
        $this->app->bind(FraisServiceContract::class, FraisService::class);
        $this->app->bind(PaiementServiceContract::class, PaiementService::class);
        $this->app->bind(DossierEleveServiceContract::class, DossierEleveService::class);
        $this->app->bind(TableauHonneurServiceContract::class, TableauHonneurService::class);

        // Remplace le placeholder TransfertElevesIndisponible que Socle lie
        // par défaut (voir SocleServiceProvider) : Scolarité fournit
        // maintenant l'implémentation réelle du port. Suppose que ce
        // ServiceProvider est enregistré après celui de Socle (convention
        // du monolithe modulaire) — sinon ce bind() serait écrasé par celui
        // de Socle et le placeholder resterait actif.
        $this->app->bind(TransfertElevesAnneeContract::class, TransfertAnneeService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'scolarite');

        // Verrouillage d'année scolaire (Socle) : bloque toute écriture sur
        // les modèles financiers/inscription rattachés à une année scolaire
        // clôturée ou archivée — voir VerrouAnneeObserver, exception unique
        // du rôle Fondateur sur une année clôturée (jamais archivée).
        Inscription::observe(VerrouAnneeObserver::class);
        Classe::observe(VerrouAnneeObserver::class);
        GrilleTarifaire::observe(VerrouAnneeObserver::class);
        Paiement::observe(VerrouAnneeObserver::class);
        FraisEleve::observe(VerrouAnneeObserver::class);
        TableauHonneur::observe(VerrouAnneeObserver::class);
    }
}
