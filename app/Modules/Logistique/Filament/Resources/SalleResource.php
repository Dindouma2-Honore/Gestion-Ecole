<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources;

use App\Filament\Support\NiveauScopeSelect;
use App\Modules\Logistique\Filament\Resources\SalleResource\Pages;
use App\Modules\Logistique\Models\Salle;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SalleResource extends Resource
{
    protected static ?string $model = Salle::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|UnitEnum|null $navigationGroup = 'Logistique';

    protected static ?string $navigationLabel = 'Salles';

    protected static ?string $modelLabel = 'salle';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nom')->label('Nom')->required()->maxLength(255),

                Select::make('type')
                    ->label('Type')
                    ->options([
                        'classe' => 'Classe', 'bureau' => 'Bureau',
                        'laboratoire' => 'Laboratoire', 'terrain' => 'Terrain', 'autre' => 'Autre',
                    ])
                    ->default('classe')
                    ->required(),

                TextInput::make('capacite')->label('Capacité')->numeric(),

                NiveauScopeSelect::make(),

                Select::make('etat')
                    ->label('État')
                    ->options([
                        'bon' => 'Bon', 'a_renover' => 'À rénover', 'hors_service' => 'Hors service',
                    ])
                    ->default('bon')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nom')->label('Nom')->searchable(),
                Tables\Columns\TextColumn::make('type')->label('Type')->badge(),
                Tables\Columns\TextColumn::make('capacite')->label('Capacité')->placeholder('—'),
                Tables\Columns\TextColumn::make('etat')
                    ->label('État')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'bon' => 'success',
                        'a_renover' => 'warning',
                        'hors_service' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('etat')->options([
                    'bon' => 'Bon', 'a_renover' => 'À rénover', 'hors_service' => 'Hors service',
                ]),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalles::route('/'),
            'create' => Pages\CreateSalle::route('/create'),
            'edit' => Pages\EditSalle::route('/{record}/edit'),
        ];
    }
}
