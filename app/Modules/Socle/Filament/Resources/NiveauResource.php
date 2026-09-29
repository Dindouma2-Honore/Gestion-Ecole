<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Modules\Socle\Models\Niveau;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NiveauResource extends Resource
{
    protected static ?string $model = Niveau::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|\UnitEnum|null $navigationGroup = 'Structure de l’établissement';

    protected static ?string $modelLabel = 'Niveau d\'enseignement';

    protected static ?string $pluralModelLabel = 'Niveaux d\'enseignement';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('cycle_id')->label('Cycle')->relationship('cycle', 'nom')->searchable()->preload(),
                TextInput::make('nom')
                    ->required(),
                TextInput::make('code')
                    ->required(),
                TextInput::make('ordre')
                    ->numeric()
                    ->default(1)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ordre')->sortable(),
                TextColumn::make('cycle.nom')->label('Cycle'),
                TextColumn::make('nom')->searchable()->sortable(),
                TextColumn::make('code')->searchable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => NiveauResource\Pages\ListNiveaux::route('/'),
            'create' => NiveauResource\Pages\CreateNiveau::route('/create'),
            'edit' => NiveauResource\Pages\EditNiveau::route('/{record}/edit'),
        ];
    }
}
