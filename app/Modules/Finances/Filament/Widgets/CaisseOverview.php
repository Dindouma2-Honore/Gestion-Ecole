<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Widgets;

use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Models\SessionCaisse;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class CaisseOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    #[On('caisse-updated')]
    public function refreshCaisse(): void {}

    protected function getStats(): array
    {
        $session = SessionCaisse::where('statut', 'ouverte')->oldest('date_session')->oldest('id')->first()
            ?? SessionCaisse::latest('date_session')->latest('id')->first();
        $ouverte = $session?->statut === 'ouverte';
        $format = fn ($value): string => $value === null ? '—' : number_format((float) $value, 0, ',', ' ').' FCFA';
        $theorique = $ouverte ? app(CaisseServiceContract::class)->getSoldeTheoriqueActuel() : $session?->solde_cloture_theorique;
        $ecart = $session?->ecart;

        return [
            Stat::make('Session de caisse', $session ? ($ouverte ? 'Ouverte' : 'Clôturée') : 'Aucune session')
                ->description($session ? 'Session du '.$session->date_session->format('d/m/Y') : 'Ouvrez la caisse pour démarrer.')
                ->icon('heroicon-o-lock-closed')->color($ouverte ? 'success' : 'gray'),
            Stat::make('Solde théorique en espèces', $format($theorique))->description('Hors banque et Mobile Money'),
            Stat::make('Montant physique compté', $format($session?->solde_cloture_reel))->description($ouverte ? 'À saisir à la clôture' : 'Montant enregistré lors de la clôture'),
            Stat::make('Écart de caisse', $format($ecart))
                ->description($ecart === null ? 'À calculer à la clôture' : ((float) $ecart < 0 ? 'Déficit : montant physique inférieur au théorique' : ((float) $ecart > 0 ? 'Excédent : montant physique supérieur au théorique' : 'Caisse conforme')))
                ->color($ecart === null ? 'gray' : ((float) $ecart === 0.0 ? 'success' : 'danger')),
        ];
    }
}
