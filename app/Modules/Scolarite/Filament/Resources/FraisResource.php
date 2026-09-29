<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources;

use App\Modules\Scolarite\Filament\Resources\FraisResource\Pages;
use App\Modules\Scolarite\Models\CategorieFrais;
use App\Modules\Scolarite\Models\Frais;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class FraisResource extends Resource
{
    protected static ?string $model = Frais::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Scolarité';

    protected static ?string $navigationLabel = 'Frais';

    protected static ?string $modelLabel = 'frais';

    protected static ?string $pluralModelLabel = 'frais';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')->label('Nom')->required()->maxLength(150),

            Select::make('categorie_frais_id')
                ->label('Catégorie')
                ->options(fn () => CategorieFrais::orderBy('nom')->pluck('nom', 'id'))
                ->searchable()
                ->required(),

            Toggle::make('utilise_grille_tarifaire')
                ->label('Frais de scolarité (montant variable par classe)')
                ->helperText('Active la grille tarifaire par classe/année — sinon le montant ci-dessous est unique pour tous.')
                ->live()
                ->default(false),

            TextInput::make('montant')
                ->label('Montant')
                ->numeric()
                ->minValue(0)
                ->prefix('XAF')
                ->required(fn ($get) => ! $get('utilise_grille_tarifaire'))
                ->visible(fn ($get) => ! $get('utilise_grille_tarifaire'))
                ->helperText('Montant unique, non variable par classe (cantine, tenues, transport...).'),

            TextInput::make('ordre_repartition')
                ->label('Priorité de répartition des versements')
                ->numeric()
                ->default(999)
                ->visible(fn ($get) => (bool) $get('utilise_grille_tarifaire'))
                ->helperText('1 = frais d’inscription, 2 = tranche 1, 3 = tranche 2 (ordre par défaut). Les frais divers sont toujours imputés en dernier.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nom')->label('Nom')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('categorie.nom')->label('Catégorie')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('utilise_grille_tarifaire')->label('Grille tarifaire')->boolean(),
                Tables\Columns\TextColumn::make('montant')->label('Montant')->money('XAF')->sortable(),
                Tables\Columns\TextColumn::make('ordre_repartition')->label('Ordre de répartition')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categorie_frais_id')
                    ->label('Catégorie')
                    ->relationship('categorie', 'nom'),
            ])
            ->actions([
                Action::make('grilleTarifaire')
                    ->label('Grille tarifaire')
                    ->icon('heroicon-o-table-cells')
                    ->color('gray')
                    ->visible(fn (Frais $record) => $record->utilise_grille_tarifaire)
                    ->url(fn (Frais $record) => GrilleTarifaireResource::getUrl('index', ['tableFilters[frais_id][value]' => $record->id])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFrais::route('/'),
            'create' => Pages\CreateFrais::route('/create'),
            'edit' => Pages\EditFrais::route('/{record}/edit'),
        ];
    }
}
