<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Modules\Socle\Models\Periode;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PeriodeResource extends Resource
{
    protected static ?string $model = Periode::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-date-range';

    protected static string|\UnitEnum|null $navigationGroup = 'Années & périodes';

    protected static ?string $modelLabel = 'Période scolaire';

    protected static ?string $pluralModelLabel = 'Périodes scolaires';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('annee_scolaire_id')->label('Année scolaire')->relationship('anneeScolaire', 'libelle')->required()->searchable()->preload(),
            TextInput::make('libelle')->label('Libellé')->required()->maxLength(50),
            Select::make('type')->options(['trimestre' => 'Trimestre', 'semestre' => 'Semestre', 'sequence' => 'Séquence'])->required(),
            DatePicker::make('date_debut')->label('Date de début')->required(),
            DatePicker::make('date_fin')->label('Date de fin')->required()->afterOrEqual('date_debut'),
            TextInput::make('ordre')->numeric()->minValue(1)->maxValue(255)->required(),
            Toggle::make('cloturee')->label('Clôturée'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('anneeScolaire.libelle')->label('Année')->sortable(),
            TextColumn::make('ordre')->sortable(),
            TextColumn::make('libelle')->label('Période')->searchable(),
            TextColumn::make('type')->badge(),
            TextColumn::make('date_debut')->date('d/m/Y'),
            TextColumn::make('date_fin')->date('d/m/Y'),
            IconColumn::make('cloturee')->label('Clôturée')->boolean(),
        ])->defaultSort('ordre');
    }

    public static function getPages(): array
    {
        return [
            'index' => PeriodeResource\Pages\ListPeriodes::route('/'),
            'create' => PeriodeResource\Pages\CreatePeriode::route('/create'),
            'edit' => PeriodeResource\Pages\EditPeriode::route('/{record}/edit'),
        ];
    }
}
