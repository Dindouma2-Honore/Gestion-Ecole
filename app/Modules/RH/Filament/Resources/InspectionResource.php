<?php

namespace App\Modules\RH\Filament\Resources;

use App\Modules\RH\Models\Inspection;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InspectionResource extends Resource
{
    protected static ?string $model = Inspection::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources humaines';

    protected static ?string $modelLabel = 'Inspection pédagogique';

    protected static ?string $pluralModelLabel = 'Inspections pédagogiques';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('enseignant_id')
                ->relationship('enseignant.employe', 'nom')
                ->required(),
            DatePicker::make('date_inspection')
                ->required(),
            Select::make('inspecteur_id')
                ->relationship('inspecteur', 'name')
                ->required(),
            TextInput::make('note')
                ->numeric()
                ->minValue(0)
                ->maxValue(20),
            Textarea::make('observations'),
            Textarea::make('recommandations'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('enseignant.employe.nom_complet')->label('Enseignant')->sortable(),
                TextColumn::make('date_inspection')->date()->sortable(),
                TextColumn::make('inspecteur.name')->label('Inspecteur'),
                TextColumn::make('note')->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => InspectionResource\Pages\ListInspections::route('/'),
            'create' => InspectionResource\Pages\CreateInspection::route('/create'),
            'edit' => InspectionResource\Pages\EditInspection::route('/{record}/edit'),
        ];
    }
}
