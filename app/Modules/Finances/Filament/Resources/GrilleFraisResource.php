<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources;

use App\Modules\Finances\Models\GrilleFrais;
use App\Modules\Finances\Models\TypeFraisRecurrent;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GrilleFraisResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = GrilleFrais::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?string $modelLabel = 'Grille de frais';

    protected static ?string $pluralModelLabel = 'Grilles de frais';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('niveau_id')
                    ->label('Niveau')
                    ->options(fn (): array => collect(app(ParametrageServiceContract::class)->getTousLesNiveaux())
                        ->pluck('nom', 'id')
                        ->all())
                    ->required(),
                Select::make('annee_scolaire_id')
                    ->label('Année scolaire')
                    ->options(fn (): array => collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())
                        ->pluck('libelle', 'id')
                        ->all())
                    ->required(),
                Select::make('type_frais_recurrent_id')
                    ->label('Type de frais récurrent')
                    ->options(fn (): array => TypeFraisRecurrent::query()->with('groupe')->get()
                        ->mapWithKeys(fn (TypeFraisRecurrent $type): array => [$type->id => "{$type->nom} — {$type->groupe->nom}"])
                        ->all())
                    ->required(),
                TextInput::make('montant')
                    ->label('Montant')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('niveau_id')
                    ->label('Niveau')
                    ->formatStateUsing(fn (int $state): string => app(ParametrageServiceContract::class)->getNiveau($state)?->nom ?? "#{$state}"),
                TextColumn::make('annee_scolaire_id')
                    ->label('Année scolaire')
                    ->formatStateUsing(fn (int $state): string => app(AnneeScolaireServiceContract::class)->getAnneeScolaire($state)?->libelle ?? "#{$state}"),
                TextColumn::make('typeRecurrent.nom')
                    ->label('Type de frais')
                    ->badge()
                    ->color('info'),
                TextColumn::make('montant')
                    ->money('XAF')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type_frais_recurrent_id')
                    ->label('Type de frais')
                    ->options(fn (): array => TypeFraisRecurrent::query()->pluck('nom', 'id')->all()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => GrilleFraisResource\Pages\ListGrillesFrais::route('/'),
            'edit' => GrilleFraisResource\Pages\EditGrilleFrais::route('/{record}/edit'),
        ];
    }
}
