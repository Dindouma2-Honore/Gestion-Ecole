<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources;

use App\Modules\Logistique\Filament\Resources\EquipementResource\Pages;
use App\Modules\Logistique\Filament\Resources\EquipementResource\RelationManagers\HistoriqueLocalisationsRelationManager;
use App\Modules\Logistique\Models\Equipement;
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

class EquipementResource extends Resource
{
    protected static ?string $model = Equipement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-computer-desktop';

    protected static string|UnitEnum|null $navigationGroup = 'Logistique';

    protected static ?string $navigationLabel = 'Équipements';

    protected static ?string $modelLabel = 'équipement';

    /**
     * Distinction essentielle : ce module gère des biens individuels et
     * traçables (numéro de série), pas des quantités fongibles — ne jamais
     * mélanger avec le module Stocks (E.47).
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nom')->label('Nom')->required()->maxLength(255),
                TextInput::make('numero_identification')
                    ->label('N° d\'identification')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
                TextInput::make('categorie')->label('Catégorie')->maxLength(100),
                TextInput::make('salle_id')->label('ID salle (localisation)')->numeric(),
                TextInput::make('responsable_id')->label('ID responsable')->numeric(),
                Select::make('etat')
                    ->label('État')
                    ->options([
                        'bon' => 'Bon', 'a_reparer' => 'À réparer',
                        'hors_service' => 'Hors service', 'mis_au_rebut' => 'Mis au rebut',
                    ])
                    ->default('bon')
                    ->required(),
                DatePicker::make('date_acquisition')->label('Date d\'acquisition')->required(),
                TextInput::make('valeur_acquisition')->label('Valeur d\'acquisition')->numeric()->prefix('FCFA'),
                DatePicker::make('garantie_fin')->label('Fin de garantie'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nom')->label('Nom')->searchable(),
                Tables\Columns\TextColumn::make('numero_identification')->label('N° identification')->searchable(),
                Tables\Columns\TextColumn::make('categorie')->label('Catégorie')->placeholder('—'),
                Tables\Columns\TextColumn::make('etat')
                    ->label('État')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'bon' => 'success',
                        'a_reparer' => 'warning',
                        'hors_service', 'mis_au_rebut' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('garantie_fin')->label('Garantie jusqu\'au')->date()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('etat')->options([
                    'bon' => 'Bon', 'a_reparer' => 'À réparer',
                    'hors_service' => 'Hors service', 'mis_au_rebut' => 'Mis au rebut',
                ]),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            HistoriqueLocalisationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipements::route('/'),
            'create' => Pages\CreateEquipement::route('/create'),
            'edit' => Pages\EditEquipement::route('/{record}/edit'),
        ];
    }
}
