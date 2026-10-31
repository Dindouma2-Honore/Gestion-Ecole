<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\MouvementCaisseResource\Pages;

use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Filament\Resources\MouvementCaisseResource;
use App\Modules\Finances\Filament\Widgets\CaisseOverview;
use App\Modules\Finances\Models\SessionCaisse;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;

class ListMouvementsCaisse extends ListRecords
{
    protected static string $resource = MouvementCaisseResource::class;

    protected function getHeaderWidgets(): array
    {
        return [CaisseOverview::class];
    }

    protected function getHeaderActions(): array
    {

        return [
            Action::make('ouvrir')
                ->label('Ouvrir la caisse')
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->visible(fn (): bool => ! SessionCaisse::where('statut', 'ouverte')->exists() && ! SessionCaisse::whereDate('date_session', today())->exists())
                ->schema([
                    TextInput::make('solde_ouverture')
                        ->label('Solde de départ')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(CaisseServiceContract::class)->ouvrirSession((float) $data['solde_ouverture']);
                    $this->dispatch('caisse-updated');
                }),

            Action::make('cloturer')
                ->label('Clôturer la caisse')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->visible(fn (): bool => SessionCaisse::where('statut', 'ouverte')->exists())
                ->schema([
                    Placeholder::make('theorique')->label('Montant théorique en espèces (hors banque et Mobile Money)')
                        ->content(fn (): string => number_format(app(CaisseServiceContract::class)->getSoldeTheoriqueActuel(), 0, ',', ' ').' FCFA'),
                    TextInput::make('solde_reel')
                        ->label('Montant physique compté')->suffix('FCFA')->live(debounce: 400)
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    Placeholder::make('ecart')->label('Rapprochement de caisse')
                        ->content(function (Get $get): string {
                            if (! is_numeric($get('solde_reel'))) {
                                return 'Saisissez le montant physique pour comparer.';
                            }
                            $ecart = round((float) $get('solde_reel') - app(CaisseServiceContract::class)->getSoldeTheoriqueActuel(), 2);

                            return ($ecart < 0 ? 'Déficit : ' : ($ecart > 0 ? 'Excédent : ' : 'Caisse conforme : ')).number_format(abs($ecart), 0, ',', ' ').' FCFA';
                        }),
                ])
                ->action(function (array $data): void {
                    $session = app(CaisseServiceContract::class)->cloturerSession((float) $data['solde_reel']);
                    $this->dispatch('caisse-updated');
                    $format = fn ($value): string => number_format((float) $value, 0, ',', ' ').' FCFA';
                    Notification::make()->success()->title('Caisse clôturée')
                        ->body('Théorique : '.$format($session->solde_cloture_theorique).' — Compté : '.$format($session->solde_cloture_reel).' — Écart : '.$format($session->ecart))
                        ->persistent()->send();
                }),
        ];
    }
}
