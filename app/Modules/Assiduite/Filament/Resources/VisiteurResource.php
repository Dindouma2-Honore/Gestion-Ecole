<?php

namespace App\Modules\Assiduite\Filament\Resources;

use App\Modules\Assiduite\Filament\Resources\VisiteurResource\Pages;
use App\Modules\Assiduite\Models\Visiteur;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class VisiteurResource extends Resource
{
    protected static ?string $model = Visiteur::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|\UnitEnum|null $navigationGroup = 'Assiduité';

    protected static ?string $modelLabel = 'Visiteur';

    protected static ?string $pluralModelLabel = 'Registre des Visiteurs';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')
                ->required()
                ->maxLength(255),
            TextInput::make('telephone')
                ->tel()
                ->maxLength(20),
            TextInput::make('motif')
                ->required()
                ->maxLength(255),
            TextInput::make('badge_numero')
                ->label('N° de badge')
                ->maxLength(20),
            DateTimePicker::make('heure_entree')
                ->default(now())
                ->required(),
            DateTimePicker::make('heure_sortie'),
            Toggle::make('autorisation_prealable')
                ->label('Autorisation préalable')
                ->default(false),
            Toggle::make('incident_signale')
                ->label('Incident signalé')
                ->default(false),
            Select::make('enregistre_par')
                ->relationship('enregistrePar', 'name')
                ->default(fn () => Auth::id())
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nom')->searchable()->sortable(),
                TextColumn::make('telephone')->searchable(),
                TextColumn::make('motif')->limit(30),
                TextColumn::make('badge_numero')->label('Badge'),
                TextColumn::make('heure_entree')->dateTime()->sortable(),
                TextColumn::make('heure_sortie')->dateTime()->sortable(),
                IconColumn::make('autorisation_prealable')->boolean(),
                IconColumn::make('incident_signale')->boolean()->color('danger'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVisiteurs::route('/'),
            'create' => Pages\CreateVisiteur::route('/create'),
            'edit' => Pages\EditVisiteur::route('/{record}/edit'),
        ];
    }
}
