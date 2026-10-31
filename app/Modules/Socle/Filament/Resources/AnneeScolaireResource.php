<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Enums\AcademicYearStatus;
use App\Modules\Socle\Filament\Widgets\AnneeScolaireOverview;
use App\Modules\Socle\Models\AnneeScolaire;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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
                Select::make('period_system')
                    ->label('Système de périodes')
                    ->options(['semester' => 'Semestriel', 'trimester' => 'Trimestriel'])
                    ->default('trimester')
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
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AcademicYearStatus $state): string => match ($state) {
                        AcademicYearStatus::PREPARATION => 'Préparation',
                        AcademicYearStatus::ACTIVE => 'Active',
                        AcademicYearStatus::CLOTURE_EN_COURS => 'Clôture en cours',
                        AcademicYearStatus::CLOTUREE => 'Clôturée',
                        AcademicYearStatus::ARCHIVEE => 'Archivée',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'preparation' => 'Préparation', 'active' => 'Active', 'cloture_en_cours' => 'Clôture en cours', 'cloturee' => 'Clôturée', 'archivee' => 'Archivée',
                ]),
            ])
            ->recordActions([
                Action::make('activer')
                    ->label('Activer')->icon('heroicon-o-play')->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AnneeScolaire $record): bool => $record->status === AcademicYearStatus::PREPARATION)
                    ->action(fn (AnneeScolaire $record) => app(AnneeScolaireServiceContract::class)->activerAnnee($record->id)),
                Action::make('cloturer')
                    ->label('Clôturer')->icon('heroicon-o-lock-closed')->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AnneeScolaire $record): bool => $record->status === AcademicYearStatus::ACTIVE)
                    ->action(fn (AnneeScolaire $record) => app(AnneeScolaireServiceContract::class)->cloturerAnnee($record->id)),
                Action::make('archiver')
                    ->label('Archiver')->icon('heroicon-o-archive-box')->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (AnneeScolaire $record): bool => $record->status === AcademicYearStatus::CLOTUREE)
                    ->action(fn (AnneeScolaire $record) => app(AnneeScolaireServiceContract::class)->archiverAnnee($record->id)),
                EditAction::make()->visible(fn (AnneeScolaire $record): bool => ! $record->isReadonly()),
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
