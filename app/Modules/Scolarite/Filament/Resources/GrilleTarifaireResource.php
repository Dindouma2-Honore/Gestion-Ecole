<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources;

use App\Modules\Scolarite\Filament\Resources\GrilleTarifaireResource\Pages;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Frais;
use App\Modules\Scolarite\Models\GrilleTarifaire;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * S'applique uniquement aux frais de la catégorie "Frais de scolarité"
 * (inscription, tranche 1, tranche 2) — voir Frais::utilise_grille_tarifaire.
 */
class GrilleTarifaireResource extends Resource
{
    protected static ?string $model = GrilleTarifaire::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static string|UnitEnum|null $navigationGroup = 'Scolarité';

    protected static ?string $navigationLabel = 'Grille tarifaire';

    protected static ?string $modelLabel = 'tarif';

    protected static ?string $pluralModelLabel = 'grille tarifaire';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('frais_id')
                ->label('Frais de scolarité')
                ->options(fn () => Frais::where('utilise_grille_tarifaire', true)->pluck('nom', 'id'))
                ->searchable()
                ->required(),

            Select::make('classe_id')
                ->label('Classe')
                ->options(fn () => Classe::orderBy('nom')->pluck('nom', 'id'))
                ->searchable()
                ->required(),

            Select::make('annee_scolaire_id')
                ->label('Année scolaire')
                ->options(fn () => self::optionsAnneesScolaires())
                ->searchable()
                ->required(),

            TextInput::make('montant')
                ->label('Montant')
                ->numeric()
                ->minValue(0)
                ->prefix('XAF')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $annees = self::optionsAnneesScolaires();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('frais.nom')->label('Frais')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('classe.nom')->label('Classe')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('annee_scolaire_id')
                    ->label('Année scolaire')
                    ->formatStateUsing(fn ($state) => $annees[$state] ?? '—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('montant')->label('Montant')->money('XAF')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('frais_id')
                    ->label('Frais')
                    ->relationship('frais', 'nom'),
                Tables\Filters\SelectFilter::make('classe_id')
                    ->label('Classe')
                    ->relationship('classe', 'nom'),
                Tables\Filters\SelectFilter::make('annee_scolaire_id')
                    ->label('Année scolaire')
                    ->options(fn () => self::optionsAnneesScolaires()),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGrilleTarifaire::route('/'),
            'create' => Pages\CreateGrilleTarifaire::route('/create'),
            'edit' => Pages\EditGrilleTarifaire::route('/{record}/edit'),
        ];
    }

    /**
     * Année scolaire (Socle) : pas de relation Eloquent inter-module, simple
     * lecture de la table pour peupler le Select (voir ClasseResource).
     *
     * @return Collection<int, string>
     */
    private static function optionsAnneesScolaires(): Collection
    {
        try {
            return DB::table('annees_scolaires')->orderByDesc('id')->pluck('libelle', 'id');
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
