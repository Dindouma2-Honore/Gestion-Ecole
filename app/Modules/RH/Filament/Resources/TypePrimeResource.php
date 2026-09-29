<?php

namespace App\Modules\RH\Filament\Resources;

use App\Modules\RH\Models\TypePrime;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TypePrimeResource extends Resource
{
    protected static ?string $model = TypePrime::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources humaines';

    protected static ?string $modelLabel = 'Type de prime';

    protected static ?string $pluralModelLabel = 'Types de primes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('Code automatique')
                ->disabled()
                ->dehydrated(false)
                ->visibleOn('edit')
                ->maxLength(50),
            TextInput::make('libelle')
                ->required()
                ->maxLength(100),
            Select::make('mode_calcul')
                ->options([
                    'montant_fixe' => 'Montant fixe',
                    'pourcentage_salaire' => 'Pourcentage du salaire de base',
                ])
                ->required(),
            TextInput::make('valeur_defaut')
                ->label('Valeur')
                ->numeric()
                ->default(0.00)
                ->required(),
            Toggle::make('actif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->sortable()->searchable(),
                TextColumn::make('libelle')->sortable()->searchable(),
                TextColumn::make('mode_calcul')->label('Calcul')->badge()->formatStateUsing(fn (string $state): string => $state === 'pourcentage_salaire' ? 'Pourcentage du salaire' : 'Montant fixe'),
                TextColumn::make('valeur_defaut')->label('Valeur')->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => TypePrimeResource\Pages\ListTypePrimes::route('/'),
            'create' => TypePrimeResource\Pages\CreateTypePrime::route('/create'),
            'edit' => TypePrimeResource\Pages\EditTypePrime::route('/{record}/edit'),
        ];
    }
}
