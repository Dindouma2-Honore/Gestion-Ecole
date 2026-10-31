<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources;

use App\Filament\Support\RecordModal;
use App\Modules\Logistique\Contracts\CatalogueMaterielInterface;
use App\Modules\Logistique\Enums\NatureMateriel;
use App\Modules\Logistique\Filament\Resources\CategorieMaterielResource\Pages;
use App\Modules\Logistique\Models\CategorieMateriel;
use App\Modules\Logistique\Models\Equipement;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class CategorieMaterielResource extends Resource
{
    protected static ?string $model = CategorieMateriel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Logistique';

    protected static ?string $navigationLabel = 'Catégories de matériel';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'catégorie de matériel';

    protected static ?string $pluralModelLabel = 'catégories de matériel';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(60)
                    ->unique(ignoreRecord: true)
                    ->helperText('Identifiant unique du catalogue, ex : MAT-PC.'),
                TextInput::make('nom')
                    ->label('Nom')
                    ->required()
                    ->maxLength(120),
                Select::make('nature')
                    ->label('Nature')
                    ->options(static::optionsNature())
                    ->required()
                    ->default(NatureMateriel::Durable->value)
                    ->helperText('La nature appartient au catalogue : elle s’applique à tous les équipements de la catégorie.'),
                TextInput::make('ordre')
                    ->label('Ordre d’affichage')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        $table = $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('nom')->label('Nom')->searchable(),
                Tables\Columns\TextColumn::make('nature')
                    ->label('Nature')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof NatureMateriel ? $state->label() : (string) $state)
                    ->color(fn ($state): string => self::estConsommable($state) ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('ordre')->label('Ordre'),
                Tables\Columns\IconColumn::make('actif')->label('Active')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('nature')
                    ->label('Nature')
                    ->options(static::optionsNature()),
                Tables\Filters\SelectFilter::make('actif')
                    ->label('État')
                    ->options(['1' => 'Active', '0' => 'Inactive']),
            ])
            ->actions([
                Action::make('basculerActivation')
                    ->label(fn (CategorieMateriel $record): string => $record->actif ? 'Désactiver' : 'Activer')
                    ->icon(fn (CategorieMateriel $record): string => $record->actif ? 'heroicon-o-x-circle' : 'heroicon-o-check-badge')
                    ->color(fn (CategorieMateriel $record): string => $record->actif ? 'danger' : 'success')
                    ->action(function (CategorieMateriel $record): void {
                        $catalogue = app(CatalogueMaterielInterface::class);

                        if ($record->actif) {
                            $catalogue->desactiver((int) $record->id);
                        } else {
                            $catalogue->activer((int) $record->id);
                        }
                    }),
                EditAction::make(),
                // Aucune suppression physique d'une catégorie déjà utilisée :
                // l'action reste masquée tant qu'un équipement la référence,
                // et le service refuse aussi toute suppression directe.
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->action(function (CategorieMateriel $record): void {
                        app(CatalogueMaterielInterface::class)->supprimer((int) $record->id);
                    })
                    ->visible(fn (CategorieMateriel $record): bool => Equipement::query()->where('categorie_id', $record->id)->doesntExist()),
            ]);

        return RecordModal::configure($table, static::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategoriesMateriel::route('/'),
            'create' => Pages\CreateCategorieMateriel::route('/create'),
            'edit' => Pages\EditCategorieMateriel::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function optionsNature(): array
    {
        return array_reduce(
            NatureMateriel::cases(),
            fn (array $options, NatureMateriel $nature): array => [...$options, $nature->value => $nature->label()],
            [],
        );
    }

    private static function estConsommable(mixed $state): bool
    {
        if ($state instanceof NatureMateriel) {
            return $state === NatureMateriel::Consommable;
        }

        return mb_strtolower((string) $state) === NatureMateriel::Consommable->value;
    }
}
