<?php

namespace App\Modules\Assiduite\Filament\Resources;

use App\Modules\Assiduite\Filament\Resources\SortieEleveResource\Pages;
use App\Modules\Assiduite\Models\SortieEleve;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SortieEleveResource extends Resource
{
    protected static ?string $model = SortieEleve::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-right-start-on-rectangle';

    protected static string|\UnitEnum|null $navigationGroup = 'Assiduité';

    protected static ?string $modelLabel = 'Sortie d\'Élève';

    protected static ?string $pluralModelLabel = 'Sorties d\'Élèves';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('eleve_id')
                ->relationship('eleve', 'nom')
                ->required()
                ->searchable(),
            Select::make('parent_id')
                ->label('Parent / Tuteur (Sortie normale)')
                ->relationship('parent', 'nom')
                ->searchable(),
            TextInput::make('personne_autorisee_nom')
                ->label('Nom personne (Sortie exceptionnelle)')
                ->maxLength(255),
            Select::make('type')
                ->options([
                    'normale' => 'Normale',
                    'exceptionnelle' => 'Exceptionnelle',
                ])
                ->default('normale')
                ->required(),
            DateTimePicker::make('heure_sortie')
                ->default(now())
                ->required(),
            Select::make('remis_par')
                ->relationship('remisPar', 'name')
                ->default(fn () => Auth::id())
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('eleve.nom')->label('Élève')->sortable()->searchable(),
                TextColumn::make('parent.nom')->label('Parent / Tuteur')->sortable(),
                TextColumn::make('personne_autorisee_nom')->label('Personne autorisée'),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'normale' => 'success',
                        'exceptionnelle' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('heure_sortie')->dateTime()->sortable(),
                TextColumn::make('remisPar.name')->label('Remis par'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSortiesEleves::route('/'),
            'create' => Pages\CreateSortieEleve::route('/create'),
            'edit' => Pages\EditSortieEleve::route('/{record}/edit'),
        ];
    }
}
