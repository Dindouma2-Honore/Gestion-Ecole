<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Modules\Finances\Contracts\BalanceServiceContract;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Contracts\FraisScolaireServiceContract;
use App\Modules\Finances\Exceptions\SessionCaisseIntrouvableException;
use App\Modules\Finances\Filament\Pages\BalancePeriode;
use App\Modules\Finances\Filament\Pages\SituationFinanciere;
use App\Modules\Finances\Filament\Resources\MouvementCaisseResource;
use App\Modules\RH\Contracts\PaieServiceContract;
use App\Modules\RH\Filament\Resources\BulletinPaieResource;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Vue d'ensemble de l'établissement, à ne pas confondre avec la Situation
 * des élèves (Finances\Filament\Pages\SituationFinanciere, inchangée).
 *
 * Les 4 indicateurs viennent volontairement de deux sources distinctes :
 * solde caisse et dépenses du mois sont des mouvements déjà survenus
 * (Caisse E41) ; impayés élèves et salaires dus sont des obligations pas
 * encore réglées, donc absentes de la Caisse par construction — elles ne
 * doivent jamais être recalculées à partir des mouvements de Caisse.
 */
class SituationEtablissement extends Page
{
    public static function getNavigationLabel(): string
    {
        return __('interface.institution_status');
    }

    protected string $view = 'filament.pages.situation-etablissement';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?string $navigationLabel = "Situation de l'établissement";

    protected static ?int $navigationSort = 5;

    protected static ?string $title = "Situation de l'établissement";

    protected static ?string $slug = 'finances/situation-etablissement';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasAnyRole(['Fondateur', 'Comptable']) ?? false;
    }

    /** @return array{soldeCaisse: ?float, depensesDuMois: float, totalImpayes: float, salairesDus: float, urls: array<string,string>} */
    protected function getViewData(): array
    {
        $maintenant = CarbonImmutable::now();

        try {
            $soldeCaisse = app(CaisseServiceContract::class)->getSoldeTheoriqueActuel();
        } catch (SessionCaisseIntrouvableException) {
            $soldeCaisse = null;
        }

        $depensesDuMois = app(BalanceServiceContract::class)
            ->getBalance($maintenant->startOfMonth(), $maintenant->endOfMonth())
            ->total_sorties;

        $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();
        $totalImpayes = app(FraisScolaireServiceContract::class)->getTotalImpayes($anneeId);

        try {
            $salairesDus = app(PaieServiceContract::class)->getSalairesDus($maintenant->month, $maintenant->year);
        } catch (Throwable) {
            $salairesDus = 0.0;
        }

        return [
            'soldeCaisse' => $soldeCaisse,
            'depensesDuMois' => $depensesDuMois,
            'totalImpayes' => $totalImpayes,
            'salairesDus' => $salairesDus,
            'urls' => [
                'caisse' => MouvementCaisseResource::getUrl('index'),
                'depenses' => BalancePeriode::getUrl(),
                'impayes' => SituationFinanciere::getUrl(),
                'salaires' => BulletinPaieResource::getUrl('index'),
            ],
        ];
    }
}
