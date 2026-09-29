<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Filament\Widgets\AnneeScolaireOverview;
use App\Modules\Socle\Models\AnneeScolaire;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AnneeScolaireResource extends Resource
{
    protected static ?string $model = AnneeScolaire::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string|\UnitEnum|null $navigationGroup = 'Années & périodes';

    protected static ?string $modelLabel = 'Année Scolaire';

    protected static ?string $pluralModelLabel = 'Années Scolaires';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('libelle')
                    ->required()
                    ->placeholder('2026-2027'),
                DatePicker::make('date_debut')
                    ->required(),
                DatePicker::make('date_fin')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('libelle')->sortable()->searchable(),
                TextColumn::make('date_debut')->date(),
                TextColumn::make('date_fin')->date(),
                TextColumn::make('statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AmbassadorsDesign::statutLabels()[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => AmbassadorsDesign::statutColor($state)),
            ])
            ->filters([
                SelectFilter::make('statut')->options([
                    'brouillon' => 'Brouillon', 'active' => 'Active', 'cloturee' => 'Clôturée', 'archivee' => 'Archivée',
                ]),
            ])
            ->recordActions([
                Action::make('activer')
                    ->label('Activer')->icon('heroicon-o-play')->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AnneeScolaire $record): bool => $record->statut === AnneeScolaire::STATUT_BROUILLON)
                    ->action(fn (AnneeScolaire $record) => app(AnneeScolaireServiceContract::class)->activerAnnee($record->id)),
                Action::make('cloturer')
                    ->label('Clôturer')->icon('heroicon-o-lock-closed')->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AnneeScolaire $record): bool => $record->statut === AnneeScolaire::STATUT_ACTIVE)
                    ->action(fn (AnneeScolaire $record) => app(AnneeScolaireServiceContract::class)->cloturerAnnee($record->id)),
                Action::make('archiver')
                    ->label('Archiver')->icon('heroicon-o-archive-box')->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (AnneeScolaire $record): bool => $record->statut === AnneeScolaire::STATUT_CLOTUREE)
                    ->action(fn (AnneeScolaire $record) => app(AnneeScolaireServiceContract::class)->archiverAnnee($record->id)),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => AnneeScolaireResource\Pages\ListAnneeScolaires::route('/'),
            'create' => AnneeScolaireResource\Pages\CreateAnneeScolaire::route('/create'),
            'edit' => AnneeScolaireResource\Pages\EditAnneeScolaire::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [AnneeScolaireOverview::class];
    }
}
