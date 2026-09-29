<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources;

use App\Modules\Logistique\Filament\Resources\TravauxInfrastructureResource\Pages;
use App\Modules\Logistique\Models\TravauxInfrastructure;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class TravauxInfrastructureResource extends Resource
{
    protected static ?string $model = TravauxInfrastructure::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench';

    protected static string|UnitEnum|null $navigationGroup = 'Logistique';

    protected static ?string $navigationLabel = 'Travaux';

    protected static ?string $modelLabel = 'travaux';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('salle_id')->label('ID salle')->numeric()->required(),
                Textarea::make('description')->label('Description')->required()->columnSpanFull(),
                DatePicker::make('date_debut')->label('Date de début')->required(),
                DatePicker::make('date_fin_prevue')->label('Date de fin prévue'),
                TextInput::make('cout')->label('Coût')->numeric()->prefix('FCFA'),
                Select::make('statut')
                    ->label('Statut')
                    ->options(['planifie' => 'Planifié', 'en_cours' => 'En cours', 'termine' => 'Terminé'])
                    ->default('planifie')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('salle_id')->label('Salle (ID)'),
                Tables\Columns\TextColumn::make('description')->label('Description')->limit(50),
                Tables\Columns\TextColumn::make('date_debut')->label('Début')->date(),
                Tables\Columns\TextColumn::make('date_fin_prevue')->label('Fin prévue')->date()->placeholder('—'),
                Tables\Columns\TextColumn::make('statut')->label('Statut')->badge(),
            ])
            ->defaultSort('date_debut', 'desc')
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTravauxInfrastructures::route('/'),
            'create' => Pages\CreateTravauxInfrastructure::route('/create'),
            'edit' => Pages\EditTravauxInfrastructure::route('/{record}/edit'),
        ];
    }
}
