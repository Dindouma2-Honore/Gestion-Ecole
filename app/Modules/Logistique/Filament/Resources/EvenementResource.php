<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources;

use App\Filament\Support\RecordModal;
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
use Illuminate\Support\Facades\DB;
use UnitEnum;

class EvenementResource extends Resource
{
    protected static ?string $model = Evenement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Logistique';

    protected static ?string $navigationLabel = 'Événements';

    protected static bool $shouldRegisterNavigation = false;

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
                TextInput::make('reference')->label('Référence')->unique(ignoreRecord: true),
                TextInput::make('titre')->label('Titre')->required()->maxLength(255),
                Select::make('type')->label('Type')->options(['rentree' => 'Rentrée scolaire', 'bulletins' => 'Remise des bulletins', 'conseil' => 'Conseil de classe', 'portes_ouvertes' => 'Portes ouvertes', 'culture' => 'Journée culturelle', 'sport' => 'Compétition sportive', 'sortie' => 'Sortie éducative', 'parents' => 'Réunion des parents', 'autre' => 'Autre'])->default('autre')->required(),
                Textarea::make('description')->label('Description')->columnSpanFull(),
                DateTimePicker::make('date_debut')->label('Début')->required(),
                DateTimePicker::make('date_fin')->label('Fin')->required(),
                TextInput::make('lieu')->label('Lieu')->maxLength(255),
                Select::make('salle_id')->label('Espace requis')->searchable()->preload()->options(fn () => DB::table('salles')->orderBy('nom')->pluck('nom', 'id')->all()),
                TextInput::make('budget_prevu')->label('Budget prévu')->numeric()->prefix('FCFA'),
                Toggle::make('necessite_transport')->label('Nécessite un transport'),
                Toggle::make('necessite_autorisation_parentale')->label('Nécessite une autorisation parentale'),
                Select::make('responsable_id')->label('Responsable')->searchable()->preload()->options(fn () => DB::table('users')->orderBy('name')->pluck('name', 'id')->all())->required(),
                Select::make('vehicule_id')->label('Véhicule requis')->searchable()->preload()->options(fn () => DB::table('vehicules')->orderBy('immatriculation')->pluck('immatriculation', 'id')->all()),
                Textarea::make('materiel_requis')->label('Matériel requis'),
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
        $table = $table
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

        return RecordModal::configure($table, static::class);
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
