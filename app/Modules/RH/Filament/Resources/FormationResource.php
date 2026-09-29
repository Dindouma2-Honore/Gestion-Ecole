<?php

namespace App\Modules\RH\Filament\Resources;

use App\Modules\RH\Models\Formation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FormationResource extends Resource
{
    protected static ?string $model = Formation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources humaines';

    protected static ?string $modelLabel = 'Formation continue';

    protected static ?string $pluralModelLabel = 'Formations du Personnel';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('titre')
                ->required()
                ->maxLength(255),
            Textarea::make('description'),
            DatePicker::make('date_debut')
                ->required(),
            DatePicker::make('date_fin')
                ->required(),
            TextInput::make('organisme')
                ->maxLength(150),
            TextInput::make('cout')
                ->numeric()
                ->prefix('FCFA'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titre')->sortable()->searchable(),
                TextColumn::make('organisme')->sortable(),
                TextColumn::make('date_debut')->date()->sortable(),
                TextColumn::make('date_fin')->date()->sortable(),
                TextColumn::make('cout')->money('XAF')->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => FormationResource\Pages\ListFormations::route('/'),
            'create' => FormationResource\Pages\CreateFormation::route('/create'),
            'edit' => FormationResource\Pages\EditFormation::route('/{record}/edit'),
        ];
    }
}
