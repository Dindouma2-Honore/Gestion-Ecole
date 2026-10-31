<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources;

use App\Filament\Support\RecordModal;
use App\Modules\Logistique\Contracts\CatalogueMaterielInterface;
use App\Modules\Logistique\Contracts\EquipementServiceInterface;
use App\Modules\Logistique\Enums\EtatPhysiqueMateriel;
use App\Modules\Logistique\Enums\StatutOperationnelMateriel;
use App\Modules\Logistique\Filament\Resources\EquipementResource\Pages;
use App\Modules\Logistique\Filament\Resources\EquipementResource\RelationManagers\HistoriqueLocalisationsRelationManager;
use App\Modules\Logistique\Filament\Resources\EquipementResource\RelationManagers\HistoriqueStatutsRelationManager;
use App\Modules\Logistique\Filament\Resources\EquipementResource\RelationManagers\PhotosRelationManager;
use App\Modules\Logistique\Models\Equipement;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class EquipementResource extends Resource
{
    protected static ?string $model = Equipement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-computer-desktop';

    protected static string|UnitEnum|null $navigationGroup = 'Logistique';

    protected static ?string $navigationLabel = 'Équipements';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'équipement';

    /**
     * Distinction essentielle : ce module gère des biens individuels et
     * traçables (numéro de série), pas des quantités fongibles — ne jamais
     * mélanger avec le module Stocks (E.47).
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nom')->label('Nom')->required()->maxLength(255),
                TextInput::make('numero_identification')
                    ->label('N° d\'identification')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
                Select::make('categorie_id')
                    ->label('Catégorie (catalogue)')
                    ->options(function ($state, $record): array {
                        $categorieCourante = (int) ($state ?? $record?->categorie_id ?? 0);

                        return collect(app(CatalogueMaterielInterface::class)->lister(true))
                            ->filter(fn (array $categorie): bool => $categorie['actif'] || (int) $categorie['id'] === $categorieCourante)
                            ->mapWithKeys(fn (array $categorie): array => [
                                $categorie['id'] => $categorie['actif'] ? $categorie['nom'] : "{$categorie['nom']} — inactive",
                            ])
                            ->all();
                    })
                    ->placeholder('Aucune (donnée legacy)')
                    ->helperText('Catalogue du matériel : la nature DURABLE / CONSOMMABLE est portée par la catégorie.'),
                TextInput::make('categorie')
                    ->label('Catégorie legacy')
                    ->disabled()
                    ->dehydrated(false)
                    ->hiddenOn('create')
                    ->helperText('Valeur historique conservée telle quelle, en attente de normalisation.'),
                Select::make('salle_id')->label('Localisation')->searchable()->preload()->options(fn () => DB::table('salles')->orderBy('nom')->pluck('nom', 'id')->all()),
                Select::make('responsable_id')->label('Responsable')->searchable()->preload()->options(fn () => DB::table('employes')->where('statut', 'actif')->orderBy('nom')->get()->mapWithKeys(fn ($e) => [$e->id => trim("{$e->nom} {$e->prenom}")])->all()),
                TextInput::make('fournisseur')->label('Fournisseur'),
                TextInput::make('numero_serie')->label('Numéro de série'),
                Select::make('etat')
                    ->label('État physique')
                    ->options(EtatPhysiqueMateriel::options())
                    ->default(EtatPhysiqueMateriel::Bon->value)
                    ->required(),
                Select::make('statut_operationnel')
                    ->label('Statut opérationnel')
                    ->options(StatutOperationnelMateriel::options())
                    ->default(StatutOperationnelMateriel::EnStock->value)
                    ->required(),
                DatePicker::make('date_acquisition')->label('Date d\'acquisition')->required(),
                TextInput::make('valeur_acquisition')->label('Valeur d\'acquisition')->numeric()->prefix('FCFA'),
                DatePicker::make('garantie_fin')->label('Fin de garantie'),
                Textarea::make('observations')->label('Observations')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $table = $table
            ->columns([
                Tables\Columns\TextColumn::make('nom')->label('Nom')->searchable(),
                Tables\Columns\TextColumn::make('numero_identification')->label('N° identification')->searchable(),
                Tables\Columns\TextColumn::make('categorie_libelle')
                    ->label('Catégorie')
                    ->getStateUsing(fn (Equipement $record): ?string => $record->categorie()->first()?->nom)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('nature_materiel')
                    ->label('Nature')
                    ->badge()
                    ->getStateUsing(fn (Equipement $record): ?string => $record->categorie()->first()?->nature?->label())
                    ->placeholder('—')
                    ->color(fn ($state): string => str_contains(mb_strtolower((string) $state), 'consommable') ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('salle.nom')->label('Localisation')->placeholder('—'),
                Tables\Columns\TextColumn::make('etat')
                    ->label('État physique')
                    ->badge()
                    ->formatStateUsing(fn ($state) => EtatPhysiqueMateriel::tryFrom((string) $state)?->label() ?? $state)
                    ->color(fn ($state) => match ((string) $state) {
                        'bon' => 'success',
                        'moyen' => 'info',
                        'mauvais', 'a_reparer' => 'warning',
                        'hors_service', 'mis_au_rebut' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('statut_operationnel')
                    ->label('Statut opérationnel')
                    ->badge()
                    ->formatStateUsing(fn ($state) => StatutOperationnelMateriel::tryFrom((string) $state)?->label() ?? $state)
                    ->color(fn ($state) => match ((string) $state) {
                        'en_stock' => 'success',
                        'affecte' => 'info',
                        'en_maintenance' => 'warning',
                        'loue' => 'primary',
                        'reforme' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('garantie_fin')->label('Garantie jusqu\'au')->date()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('etat')->options(EtatPhysiqueMateriel::options()),
                SelectFilter::make('statut_operationnel')->options(StatutOperationnelMateriel::options()),
                SelectFilter::make('categorie_id')
                    ->label('Catégorie (catalogue)')
                    ->options(fn (): array => collect(app(CatalogueMaterielInterface::class)->lister(true))
                        ->mapWithKeys(fn (array $categorie): array => [
                            $categorie['id'] => $categorie['actif'] ? $categorie['nom'] : "{$categorie['nom']} — inactive",
                        ])
                        ->all()),
                Filter::make('sans_categorie')
                    ->label('Sans catégorie (à normaliser)')
                    ->toggle()
                    ->query(fn ($query) => $query->whereNull('categorie_id')),
            ])
            ->actions([
                EditAction::make(),
                Action::make('changerStatut')
                    ->label('Changer statut')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Select::make('nouveau_statut')
                            ->label('Nouveau statut')
                            ->options(StatutOperationnelMateriel::options())
                            ->required(),
                        Textarea::make('motif')->label('Motif du changement'),
                    ])
                    ->action(function (Equipement $record, array $data): void {
                        app(EquipementServiceInterface::class)->changerStatut($record->id, $data['nouveau_statut'], $data['motif'] ?? null);
                    }),
            ]);

        return RecordModal::configure($table, static::class);
    }

    public static function getRelations(): array
    {
        return [
            HistoriqueLocalisationsRelationManager::class,
            HistoriqueStatutsRelationManager::class,
            PhotosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipements::route('/'),
            'create' => Pages\CreateEquipement::route('/create'),
            'edit' => Pages\EditEquipement::route('/{record}/edit'),
        ];
    }
}
