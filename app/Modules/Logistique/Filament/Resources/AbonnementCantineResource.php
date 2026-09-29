<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources;

use App\Modules\Logistique\Filament\Resources\AbonnementCantineResource\Pages;
use App\Modules\Logistique\Models\AbonnementCantine;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AbonnementCantineResource extends Resource
{
    protected static ?string $model = AbonnementCantine::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cake';

    protected static string|UnitEnum|null $navigationGroup = 'Logistique';

    protected static ?string $navigationLabel = 'Abonnements cantine';

    protected static ?string $modelLabel = 'abonnement cantine';

    /**
     * La vue "Menu du jour" avec alerte allergène visible pour le personnel
     * de cantine (mentionnée dans la doc H.63) mérite une Page Filament
     * personnalisée plutôt qu'une simple Resource CRUD — elle doit appeler
     * CantineServiceInterface::verifierCompatibiliteMenu() pour chaque
     * élève inscrit au repas du jour.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('eleve_id')
                    ->label('ID élève')
                    ->numeric()
                    ->required()
                    ->helperText('En attendant un Select alimenté par le module Scolarité.'),
                  TextInput::make('annee_scolaire_id')
    ->label('ID année scolaire')
    ->numeric()
    ->required()
    ->helperText('En attendant un Select alimenté par AnneeScolaireServiceContract (Socle).'),
                Select::make('type')
                    ->label('Type')
                    ->options(['mensuel' => 'Mensuel', 'trimestriel' => 'Trimestriel', 'annuel' => 'Annuel'])
                    ->required(),

                DatePicker::make('date_debut')->label('Date de début')->required(),
                DatePicker::make('date_fin')->label('Date de fin')->required(),
                TextInput::make('montant')->label('Montant')->numeric()->required()->prefix('FCFA'),

                Select::make('statut')
                    ->label('Statut')
                    ->options(['actif' => 'Actif', 'suspendu' => 'Suspendu', 'expire' => 'Expiré'])
                    ->default('actif')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('eleve_id')->label('Élève (ID)'),
                Tables\Columns\TextColumn::make('type')->label('Type')->badge(),
                Tables\Columns\TextColumn::make('date_debut')->label('Début')->date(),
                Tables\Columns\TextColumn::make('date_fin')->label('Fin')->date(),
                Tables\Columns\TextColumn::make('montant')->label('Montant')->money('XAF'),
                Tables\Columns\TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'actif' => 'success',
                        'suspendu' => 'warning',
                        'expire' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('statut')->options([
                    'actif' => 'Actif', 'suspendu' => 'Suspendu', 'expire' => 'Expiré',
                ]),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAbonnementsCantine::route('/'),
            'create' => Pages\CreateAbonnementCantine::route('/create'),
            'edit' => Pages\EditAbonnementCantine::route('/{record}/edit'),
        ];
    }
}
