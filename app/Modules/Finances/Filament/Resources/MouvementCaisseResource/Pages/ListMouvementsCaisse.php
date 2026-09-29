<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\MouvementCaisseResource\Pages;

use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Filament\Resources\MouvementCaisseResource;
use App\Modules\Finances\Models\SessionCaisse;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListMouvementsCaisse extends ListRecords
{
    protected static string $resource = MouvementCaisseResource::class;

    protected function getHeaderActions(): array
    {
        app(CaisseServiceContract::class)->garantirSessionOuverte();
        $sessionOuverte = SessionCaisse::query()->where('statut', 'ouverte')->exists();

        return [
            Action::make('ouvrir')
                ->label('Ouvrir la caisse')
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->visible(! $sessionOuverte)
                ->schema([
                    TextInput::make('solde_ouverture')
                        ->label('Solde de départ')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                ])
                ->action(fn (array $data) => app(CaisseServiceContract::class)
                    ->ouvrirSession((float) $data['solde_ouverture'])),

            Action::make('cloturer')
                ->label('Clôturer la caisse')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->visible($sessionOuverte)
                ->schema([
                    TextInput::make('solde_reel')
                        ->label('Solde compté')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                ])
                ->action(fn (array $data) => app(CaisseServiceContract::class)
                    ->cloturerSession((float) $data['solde_reel'])),
        ];
    }
}
