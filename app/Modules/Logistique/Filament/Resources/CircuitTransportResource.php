<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources;

use App\Modules\Logistique\Filament\Resources\CircuitTransportResource\Pages;
use App\Modules\Logistique\Models\CircuitTransport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class CircuitTransportResource extends Resource
{
    protected static ?string $model = CircuitTransport::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Logistique';

    protected static ?string $navigationLabel = 'Circuits transport';

    protected static ?string $modelLabel = 'circuit';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nom')->label('Nom du circuit')->required()->maxLength(100),

                Select::make('vehicule_id')
                    ->label('Véhicule')
                    ->relationship('vehicule', 'immatriculation')
                    ->required(),

                TextInput::make('chauffeur_id')
                    ->label('ID chauffeur')
                    ->numeric()
                    ->required()
                    ->helperText('En attendant un Select alimenté par le module RH (employés).'),

                TextInput::make('accompagnateur_id')
                    ->label('ID accompagnateur')
                    ->numeric(),

                Repeater::make('arrets')
                    ->relationship('arrets')
                    ->label('Arrêts (dans l\'ordre)')
                    ->schema([
                        TextInput::make('nom')->label('Nom de l\'arrêt')->required(),
                        TextInput::make('ordre')->label('Ordre')->numeric()->required(),
                        TimePicker::make('heure_passage_matin')->label('Passage matin'),
                        TimePicker::make('heure_passage_soir')->label('Passage soir'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nom')->label('Circuit')->searchable(),
                Tables\Columns\TextColumn::make('vehicule.immatriculation')->label('Véhicule'),
                Tables\Columns\TextColumn::make('arrets_count')->counts('arrets')->label('Nb arrêts'),
            ])
            ->actions([
                Action::make('listeEmbarquement')
                    ->label('Liste d\'embarquement')
                    ->icon('heroicon-o-printer')
                    ->url(fn (CircuitTransport $record) => static::getUrl('index')) // page d'impression dédiée à construire
                    ->openUrlInNewTab(),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCircuitsTransport::route('/'),
            'create' => Pages\CreateCircuitTransport::route('/create'),
            'edit' => Pages\EditCircuitTransport::route('/{record}/edit'),
        ];
    }
}
