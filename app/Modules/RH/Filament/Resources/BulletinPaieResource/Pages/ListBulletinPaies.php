<?php

namespace App\Modules\RH\Filament\Resources\BulletinPaieResource\Pages;

use App\Modules\RH\Contracts\PaieServiceContract;
use App\Modules\RH\Exceptions\BulletinDejaExistantException;
use App\Modules\RH\Filament\Resources\BulletinPaieResource;
use App\Modules\RH\Models\BulletinPaie;
use App\Modules\RH\Models\Employe;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ListBulletinPaies extends ListRecords
{
    protected static string $resource = BulletinPaieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('supprimer_tous')
                ->label('Supprimer tous les bulletins')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => Auth::user()?->hasRole('Fondateur') ?? false)
                ->requiresConfirmation()
                ->modalHeading('Supprimer définitivement tous les bulletins ?')
                ->modalDescription('Cette opération supprime les bulletins calculés, validés et payés ainsi que leurs lignes. Les mouvements de caisse restent conservés pour l’audit. Les déductions d’avances seront remises à zéro.')
                ->modalSubmitActionLabel('Oui, tout supprimer')
                ->action(function (): void {
                    $nombre = BulletinPaie::query()->count();
                    DB::transaction(function (): void {
                        DB::table('bulletins_paie')->delete();
                        DB::table('avances_salaires')->update(['montant_deja_deduit' => 0]);
                    });
                    Notification::make()->success()->title("{$nombre} bulletin(s) supprimé(s)")->send();
                }),
            Actions\Action::make('imprimer_tous')
                ->label('Imprimer tous les bulletins')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->schema([
                    Select::make('mois')->options(array_combine(range(1, 12), range(1, 12)))->default((int) date('n'))->required(),
                    TextInput::make('annee')->numeric()->default((int) date('Y'))->required(),
                ])
                ->action(fn (array $data) => redirect()->route('rh.bulletins-paie.imprimer-tous', [
                    'mois' => (int) $data['mois'],
                    'annee' => (int) $data['annee'],
                ])),
            Actions\Action::make('calculer_mois')
                ->label('Calculer la paie du mois')
                ->icon('heroicon-o-calculator')
                ->schema([
                    Select::make('mois')->options(array_combine(range(1, 12), range(1, 12)))->required(),
                    TextInput::make('annee')->numeric()->default((int) date('Y'))->required(),
                ])
                ->action(function (array $data): void {
                    $service = app(PaieServiceContract::class);
                    $calcules = 0;
                    Employe::query()->where('statut', 'actif')->whereHas('contrats', fn ($query) => $query->where('statut', 'actif'))->each(function (Employe $employe) use ($service, $data, &$calcules): void {
                        try {
                            $service->calculerBulletin($employe->id, (int) $data['mois'], (int) $data['annee']);
                            $calcules++;
                        } catch (BulletinDejaExistantException) {
                            // Un bulletin existant est volontairement ignoré lors du calcul collectif.
                        }
                    });
                    Notification::make()->success()->title("{$calcules} bulletin(s) calculé(s)")->send();
                }),
        ];
    }
}
