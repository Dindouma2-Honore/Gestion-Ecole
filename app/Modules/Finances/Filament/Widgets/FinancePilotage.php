<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Widgets;

use App\Filament\Pages\SituationEtablissement;
use App\Modules\Finances\Contracts\BalanceServiceContract;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Contracts\FraisScolaireServiceContract;
use App\Modules\Finances\Filament\Pages\BalancePeriode;
use App\Modules\Finances\Filament\Resources\DepenseResource;
use App\Modules\Finances\Filament\Resources\FacturePreinscriptionResource;
use App\Modules\Finances\Filament\Resources\MouvementCaisseResource;
use App\Modules\Finances\Filament\Resources\PaiementResource;
use App\Modules\Finances\Models\Depense;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Finances\Models\SessionCaisse;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Widgets\Widget;
use Throwable;

class FinancePilotage extends Widget
{
    protected string $view = 'finances::filament.widgets.finance-pilotage';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getViewData(): array
    {
        app(CaisseServiceContract::class)->garantirSessionOuverte();
        $session = SessionCaisse::query()->where('statut', 'ouverte')->first();
        $balance = app(BalanceServiceContract::class)->getBalanceDuJour();

        try {
            $solde = $session ? app(CaisseServiceContract::class)->getSoldeTheoriqueActuel() : null;
        } catch (Throwable) {
            $solde = null;
        }

        try {
            $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
            $impayes = app(FraisScolaireServiceContract::class)->getTotalImpayes($anneeId);
        } catch (Throwable) {
            $impayes = 0.0;
        }

        return [
            'sessionOuverte' => $session !== null,
            'solde' => $solde,
            'balance' => $balance,
            'impayes' => $impayes,
            'facturesAControler' => FacturePreinscription::query()->where('statut', 'en_attente_versement')->count(),
            'depensesAValider' => Depense::query()->where('statut', 'en_attente_validation')->count(),
            'urls' => [
                'caisse' => MouvementCaisseResource::getUrl('index'),
                'paiements' => PaiementResource::getUrl('index'),
                'inscriptions' => FacturePreinscriptionResource::getUrl('index'),
                'depenses' => DepenseResource::getUrl('index'),
                'situation' => SituationEtablissement::getUrl(),
                'balance' => BalancePeriode::getUrl(),
            ],
        ];
    }
}
