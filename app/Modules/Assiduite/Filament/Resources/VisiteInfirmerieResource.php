<?php

namespace App\Modules\Assiduite\Filament\Resources;

use App\Modules\Assiduite\Filament\Resources\VisiteInfirmerieResource\Pages;
use App\Modules\Assiduite\Models\VisiteInfirmerie;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class VisiteInfirmerieResource extends Resource
{
    protected static ?string $model = VisiteInfirmerie::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-plus-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Assiduité';

    protected static ?string $modelLabel = 'Visite Infirmerie';

    protected static ?string $pluralModelLabel = 'Visites Infirmerie';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('eleve_id')
                ->relationship('eleve', 'nom')
                ->required()
                ->searchable(),
            DateTimePicker::make('date_heure')
                ->default(now())
                ->required(),
            Textarea::make('motif')
                ->required(),
            Textarea::make('soins_prodigues'),
            TextInput::make('medicament_administre')
                ->maxLength(255),
            Select::make('gravite')
                ->options([
                    'mineure' => 'Mineure',
                    'moderee' => 'Modérée',
                    'grave' => 'Grave',
                ])
                ->default('mineure')
                ->required(),
            Toggle::make('evacuation_necessaire')
                ->default(false),
            Toggle::make('parent_notifie')
                ->default(false),
            Select::make('traite_par')
                ->relationship('traitePar', 'name')
                ->default(fn () => Auth::id())
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('eleve.nom')->label('Élève')->sortable()->searchable(),
                TextColumn::make('date_heure')->dateTime()->sortable(),
                TextColumn::make('motif')->limit(30),
                TextColumn::make('gravite')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'mineure' => 'info',
                        'moderee' => 'warning',
                        'grave' => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('evacuation_necessaire')->boolean()->label('Évacuation'),
                IconColumn::make('parent_notifie')->boolean()->label('Notifié'),
                TextColumn::make('traitePar.name')->label('Soignant'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVisitesInfirmerie::route('/'),
            'create' => Pages\CreateVisiteInfirmerie::route('/create'),
            'edit' => Pages\EditVisiteInfirmerie::route('/{record}/edit'),
        ];
    }
}
