<?php

namespace App\Modules\RH\Filament\Resources;

use App\Modules\RH\Models\Prime;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrimeResource extends Resource
{
    protected static ?string $model = Prime::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources humaines';

    protected static ?string $modelLabel = 'Prime attribuée';

    protected static ?string $pluralModelLabel = 'Primes & Gratifications';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employe_id')
                ->relationship('employe', 'nom')
                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->nom} {$record->prenom}")
                ->searchable()
                ->required(),
            Select::make('type_prime_id')
                ->relationship('typePrime', 'libelle')
                ->required(),
            TextInput::make('montant')
                ->numeric()
                ->prefix('FCFA')
                ->required(),
            TextInput::make('mois')
                ->numeric()
                ->minValue(1)
                ->maxValue(12)
                ->required(),
            TextInput::make('annee')
                ->numeric()
                ->required(),
            Textarea::make('justification'),
            Select::make('statut')
                ->options([
                    'proposee' => 'Proposée',
                    'validee' => 'Validée',
                    'integree_paie' => 'Intégrée en Paie',
                    'rejetee' => 'Rejetée',
                ])
                ->default('proposee')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employe.nom_complet')->label('Employé')->sortable()->searchable(),
                TextColumn::make('typePrime.libelle')->label('Type Prime')->sortable(),
                TextColumn::make('montant')->money('XAF')->sortable(),
                TextColumn::make('mois')->sortable(),
                TextColumn::make('annee')->sortable(),
                TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'validee', 'integree_paie' => 'success',
                        'proposee' => 'warning',
                        'rejetee' => 'danger',
                        default => 'gray',
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => PrimeResource\Pages\ListPrimes::route('/'),
            'create' => PrimeResource\Pages\CreatePrime::route('/create'),
            'edit' => PrimeResource\Pages\EditPrime::route('/{record}/edit'),
        ];
    }
}
