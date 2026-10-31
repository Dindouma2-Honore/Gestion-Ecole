<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\FactureResource\Pages;

use App\Modules\Scolarite\Filament\Resources\FactureResource;
use App\Modules\Scolarite\Models\FactureGeneree;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ViewFacture extends ViewRecord
{
    protected static string $resource = FactureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('imprimer')
                ->label('Imprimer la facture')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (FactureGeneree $record): string => route('impressions.facture', ['facture' => $record->id]))
                ->openUrlInNewTab(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            View::make('scolarite::filament.pages.facture-details-table')
                ->viewData(fn (): array => ['record' => $this->record->loadMissing(['inscription.eleve', 'inscription.classe'])])
                ->columnSpanFull(),
        ]);
    }
}
