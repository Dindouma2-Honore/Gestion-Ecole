<?php

namespace App\Modules\RH\Filament\Resources;

use App\Modules\RH\Models\PersonnelPrime;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PersonnelPrimeResource extends Resource
{
    protected static ?string $model = PersonnelPrime::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Attribution de prime';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employe_id')->label('Personnel')->relationship('employe', 'nom')->getOptionLabelFromRecordUsing(fn ($record): string => $record->nom_complet)->searchable()->required(),
            Select::make('type_prime_id')->label('Prime')->relationship('typePrime', 'libelle')->searchable()->required(),
            TextInput::make('valeur_override')->label('Valeur personnalisée')->numeric()->helperText('Vide = valeur du catalogue.'),
            DatePicker::make('date_attribution')->label('Début')->default(today())->required(),
            DatePicker::make('date_fin')->label('Fin')->afterOrEqual('date_attribution'),
            Textarea::make('motif')->required(),
            Toggle::make('actif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('employe.nom_complet')->label('Personnel')->searchable(),
            TextColumn::make('typePrime.libelle')->label('Prime'),
            TextColumn::make('typePrime.mode_calcul')->label('Calcul')->badge(),
            TextColumn::make('valeur_override')->label('Surcharge'),
            TextColumn::make('date_attribution')->date(), TextColumn::make('date_fin')->date(), IconColumn::make('actif')->boolean(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => PersonnelPrimeResource\Pages\ListPersonnelPrimes::route('/'), 'create' => PersonnelPrimeResource\Pages\CreatePersonnelPrime::route('/create'), 'edit' => PersonnelPrimeResource\Pages\EditPersonnelPrime::route('/{record}/edit')];
    }
}
