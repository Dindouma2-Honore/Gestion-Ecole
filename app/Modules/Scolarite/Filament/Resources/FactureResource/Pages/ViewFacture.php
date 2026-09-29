<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\FactureResource\Pages;

use App\Modules\Scolarite\Filament\Resources\FactureResource;
use App\Modules\Scolarite\Models\FactureGeneree;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
            Section::make('Facture')
                ->icon('heroicon-o-receipt-percent')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('numero')->label('N° facture'),
                            TextEntry::make('type')
                                ->label('Type')
                                ->badge()
                                ->formatStateUsing(fn (string $state) => $state === 'definitive' ? 'Définitive' : 'Provisoire'),
                            TextEntry::make('montant')->label('Montant')->money('XAF'),
                        ]),

                    Grid::make(2)
                        ->schema([
                            TextEntry::make('inscription.eleve.nom')
                                ->label('Élève')
                                ->formatStateUsing(fn ($record) => trim("{$record->inscription?->eleve?->prenom} {$record->inscription?->eleve?->nom}")),

                            TextEntry::make('inscription.classe.nom')->label('Classe'),
                        ]),

                    Grid::make(2)
                        ->schema([
                            TextEntry::make('nom_destinataire')->label('Destinataire')->placeholder('—'),
                            TextEntry::make('telephone_destinataire')->label('Téléphone')->placeholder('—'),
                        ]),

                    TextEntry::make('date_emission')->label('Émise le')->date('d/m/Y'),

                    TextEntry::make('statut_envoi')
                        ->label('Envoi au parent')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'en_attente' => 'En attente',
                            'envoyee' => 'Envoyée',
                            'echec' => 'Échec d’envoi',
                            default => $state,
                        })
                        ->color(fn (string $state): string => match ($state) {
                            'envoyee' => 'success',
                            'echec' => 'danger',
                            default => 'warning',
                        }),
                ])
                ->collapsible(false),
        ]);
    }
}
