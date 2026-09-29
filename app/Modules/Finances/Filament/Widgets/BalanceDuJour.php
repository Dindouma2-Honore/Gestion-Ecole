<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Widgets;

use App\Modules\Finances\Contracts\BalanceServiceContract;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BalanceDuJour extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $balance = app(BalanceServiceContract::class)->getBalanceDuJour();

        return [
            $this->montant('Entrées du jour', $balance->total_entrees, 'success', 'heroicon-o-arrow-down-circle'),
            $this->montant('Frais de scolarité', $balance->recettes_par_groupe['scolarite'], 'primary', 'heroicon-o-academic-cap'),
            $this->montant('Autres frais', $balance->recettes_par_groupe['autres'], 'info', 'heroicon-o-rectangle-stack'),
            $this->montant('Sorties du jour', $balance->total_sorties, 'danger', 'heroicon-o-arrow-up-circle'),
            $this->montant('Solde net du jour', $balance->solde_net, $balance->solde_net >= 0 ? 'primary' : 'danger', 'heroicon-o-scale'),
        ];
    }

    private function montant(string $label, float $montant, string $couleur, string $icone): Stat
    {
        return Stat::make($label, number_format($montant, 0, ',', ' ').' FCFA')
            ->icon($icone)
            ->color($couleur);
    }
}
