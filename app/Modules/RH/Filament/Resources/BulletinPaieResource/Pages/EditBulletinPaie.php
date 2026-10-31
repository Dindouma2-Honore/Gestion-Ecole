<?php

namespace App\Modules\RH\Filament\Resources\BulletinPaieResource\Pages;

use App\Modules\RH\Filament\Resources\BulletinPaieResource;
use App\Modules\RH\Models\BulletinPaie;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBulletinPaie extends EditRecord
{
    protected static string $resource = BulletinPaieResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (in_array($this->record->statut, ['valide', 'paye', 'annule'], true)) {
            abort(403, 'Un bulletin validé ou payé ne peut plus être modifié.');
        }
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        if (in_array($this->getRecord()->statut, ['valide', 'paye', 'annule'], true)) {
            abort(403, 'Un bulletin validé ou payé ne peut plus être modifié.');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('telecharger_pdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->url(fn (BulletinPaie $record): string => route('rh.bulletins-paie.telecharger', $record))
                ->openUrlInNewTab(),
            Actions\Action::make('imprimer_pdf')
                ->label('Imprimer PDF')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn (BulletinPaie $record): string => route('rh.bulletins-paie.imprimer', $record))
                ->openUrlInNewTab(),
        ];
    }
}
