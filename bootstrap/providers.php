
<?php


use App\Modules\Logistique\LogistiqueServiceProvider;
use App\Modules\Finances\Providers\FinancesServiceProvider;
use App\Modules\Pedagogie\PedagogieServiceProvider;
use App\Modules\Scolarite\ScolariteServiceProvider;
use App\Modules\Socle\Providers\SocleServiceProvider;
use App\Modules\VieScolaire\VieScolaireServiceProvider;
use App\Modules\RH\Providers\RHServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\JoelPanelProvider;

use App\Modules\Communication\Providers\CommunicationServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    JoelPanelProvider::class,
    SocleServiceProvider::class,
    FinancesServiceProvider::class,
    PedagogieServiceProvider::class,
    ScolariteServiceProvider::class,
    VieScolaireServiceProvider::class,
    LogistiqueServiceProvider::class,
    RHServiceProvider::class,
    CommunicationServiceProvider::class,
    \App\Modules\Assiduite\Providers\AssiduiteServiceProvider::class,
];
