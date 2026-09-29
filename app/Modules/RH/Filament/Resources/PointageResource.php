<?php

namespace App\Modules\RH\Filament\Resources;

use App\Modules\RH\Models\Pointage;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PointageResource extends Resource
{
    protected static ?string $model = Pointage::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources humaines';

    protected static ?string $modelLabel = 'Pointage de présence';

    protected static ?string $pluralModelLabel = 'Pointages & Assiduité';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employe_id')
                ->relationship('employe', 'nom')
                ->required(),
            DatePicker::make('date_pointage')
                ->required(),
            TimePicker::make('heure_arrivee'),
            TimePicker::make('heure_depart'),
            Select::make('mode_pointage')
                ->options([
                    'biometrique' => 'Biométrique',
                    'badgemat' => 'Badge RFID',
                    'manuel' => 'Saisie Manuelle',
                    'application' => 'Application Mobile',
                ])
                ->default('biometrique')
                ->required(),
            TextInput::make('motif_correction')
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employe.nom_complet')->label('Employé')->sortable()->searchable(),
                TextColumn::make('date_pointage')->date()->sortable(),
                TextColumn::make('heure_arrivee')->time('H:i')->sortable(),
                TextColumn::make('heure_depart')->time('H:i')->sortable(),
                TextColumn::make('mode_pointage')->badge(),
                IconColumn::make('correction_manuelle')
                    ->boolean()
                    ->label('Corrigé'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => PointageResource\Pages\ListPointages::route('/'),
            'create' => PointageResource\Pages\CreatePointage::route('/create'),
            'edit' => PointageResource\Pages\EditPointage::route('/{record}/edit'),
        ];
    }
}
