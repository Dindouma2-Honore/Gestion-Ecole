<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources;

use App\Modules\Scolarite\Filament\Resources\FactureResource\Pages;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\FactureGeneree;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class FactureResource extends Resource
{
    protected static ?string $model = FactureGeneree::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string|UnitEnum|null $navigationGroup = 'Scolarité';

    protected static ?string $navigationLabel = 'Factures';

    protected static ?string $modelLabel = 'facture';

    protected static ?string $pluralModelLabel = 'factures';

    /**
     * Une facture n'est jamais créée à la main : la provisoire est générée
     * par InscriptionServiceInterface::inscrire(), la définitive par
     * InscriptionServiceInterface::activerApresVersement() (voir
     * FactureServiceInterface).
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(FactureGeneree::query()->with(['inscription.eleve', 'inscription.classe']))
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('N° facture')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'provisoire' => 'Provisoire',
                        'definitive' => 'Définitive',
                        default => $state,
                    })
                    ->color(fn (string $state): string => $state === 'definitive' ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('inscription.eleve.nom')
                    ->label('Élève')
                    ->formatStateUsing(fn (FactureGeneree $record): string => trim("{$record->inscription?->eleve?->prenom} {$record->inscription?->eleve?->nom}"))
                    ->searchable(['inscription.eleve.nom', 'inscription.eleve.prenom']),

                Tables\Columns\TextColumn::make('inscription.classe.nom')
                    ->label('Classe')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('montant')
                    ->label('Montant')
                    ->money('XAF')
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_emission')
                    ->label('Émise le')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nom_destinataire')
                    ->label('Destinataire')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('statut_envoi')
                    ->label('Envoi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'en_attente' => 'En attente',
                        'envoyee' => 'Envoyée',
                        'echec' => 'Échec',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'envoyee' => 'success',
                        'echec' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'provisoire' => 'Provisoire',
                        'definitive' => 'Définitive',
                    ]),
                Tables\Filters\SelectFilter::make('statut_envoi')
                    ->label('Envoi')
                    ->options([
                        'en_attente' => 'En attente',
                        'envoyee' => 'Envoyée',
                        'echec' => 'Échec',
                    ]),
                Tables\Filters\SelectFilter::make('classe_id')
                    ->label('Classe')
                    ->options(fn () => Classe::orderBy('nom')->pluck('nom', 'id'))
                    ->query(fn ($query, array $data) => $query->when(
                        filled($data['value'] ?? null),
                        fn ($query) => $query->whereHas('inscription', fn ($q) => $q->where('classe_id', $data['value'])),
                    )),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('imprimer')
                    ->label('Imprimer')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (FactureGeneree $record): string => route('impressions.facture', ['facture' => $record->id]))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFactures::route('/'),
            'view' => Pages\ViewFacture::route('/{record}'),
        ];
    }
}
