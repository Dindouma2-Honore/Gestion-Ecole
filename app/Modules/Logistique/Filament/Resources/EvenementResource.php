<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources;

use App\Modules\Logistique\Filament\Resources\EvenementResource\Pages;
use App\Modules\Logistique\Filament\Resources\EvenementResource\RelationManagers\ParticipantsRelationManager;
use App\Modules\Logistique\Models\Evenement;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class EvenementResource extends Resource
{
    protected static ?string $model = Evenement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Logistique';

    protected static ?string $navigationLabel = 'Événements';

    protected static ?string $modelLabel = 'événement';

    /**
     * Upload de photos post-événement (Repeater avec le composant de
     * gestion documentaire du module Socle, A.5) — à intégrer une fois le
     * composant réutilisable identifié avec le développeur, plutôt que
     * dupliqué ici.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('titre')->label('Titre')->required()->maxLength(255),
                Textarea::make('description')->label('Description')->columnSpanFull(),
                DateTimePicker::make('date_debut')->label('Début')->required(),
                DateTimePicker::make('date_fin')->label('Fin')->required(),
                TextInput::make('lieu')->label('Lieu')->maxLength(255),
                TextInput::make('budget_prevu')->label('Budget prévu')->numeric()->prefix('FCFA'),
                Toggle::make('necessite_transport')->label('Nécessite un transport'),
                Toggle::make('necessite_autorisation_parentale')->label('Nécessite une autorisation parentale'),
                TextInput::make('responsable_id')
                    ->label('ID responsable')
                    ->numeric()
                    ->required()
                    ->helperText('users.id — en attendant un Select alimenté par le module Socle.'),
                Select::make('statut')
                    ->label('Statut')
                    ->options([
                        'planifie' => 'Planifié', 'en_cours' => 'En cours',
                        'termine' => 'Terminé', 'annule' => 'Annulé',
                    ])
                    ->default('planifie')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('titre')->label('Titre')->searchable(),
                Tables\Columns\TextColumn::make('date_debut')->label('Début')->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('lieu')->label('Lieu')->placeholder('—'),
                Tables\Columns\IconColumn::make('necessite_autorisation_parentale')
                    ->label('Autorisation requise')
                    ->boolean(),
                Tables\Columns\TextColumn::make('participants_count')
                    ->counts('participants')
                    ->label('Participants'),
                Tables\Columns\TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'planifie' => 'info',
                        'en_cours' => 'warning',
                        'termine' => 'success',
                        'annule' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('statut')->options([
                    'planifie' => 'Planifié', 'en_cours' => 'En cours',
                    'termine' => 'Terminé', 'annule' => 'Annulé',
                ]),
            ])
            ->defaultSort('date_debut', 'desc')
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ParticipantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvenements::route('/'),
            'create' => Pages\CreateEvenement::route('/create'),
            'edit' => Pages\EditEvenement::route('/{record}/edit'),
        ];
    }
}
