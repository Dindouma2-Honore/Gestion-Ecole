<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources;

use App\Modules\Finances\Models\ConfigurationFraisClasse;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConfigurationFraisClasseResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = ConfigurationFraisClasse::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?string $modelLabel = 'Frais annuels d’une classe';

    protected static ?string $pluralModelLabel = 'Frais annuels par classe';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('annee_scolaire_id')->label('Année scolaire')
                ->options(fn (): array => collect(app(AnneeScolaireServiceContract::class)->getToutesLesAnnees())->pluck('libelle', 'id')->all())
                ->required()->live(),
            Select::make('classe_id')->label('Classe')
                ->options(fn (callable $get): array => $get('annee_scolaire_id')
                    ? collect(app(ClasseServiceInterface::class)->getToutesLesClasses((int) $get('annee_scolaire_id')))->pluck('nom', 'id')->all() : [])
                ->required(),
            TextInput::make('montant_total')->label('Montant total')->numeric()->minValue(0)->suffix('FCFA')->required(),
            Select::make('politique_validation_inscription')->label('Montant requis à l’inscription')->options([
                'frais_inscription' => 'Frais d’inscription', 'premiere_tranche' => 'Première tranche',
                'inscription_et_premiere_tranche' => 'Inscription + première tranche', 'montant_minimum' => 'Montant minimum',
            ])->required(),
            TextInput::make('montant_minimum_inscription')->label('Montant minimum')->numeric()->minValue(0)->suffix('FCFA'),
            Toggle::make('actif')->default(true),
            Repeater::make('tranches')->relationship()->label('Tranches de paiement')->schema([
                TextInput::make('ordre')->numeric()->minValue(1)->required(),
                TextInput::make('libelle')->required()->maxLength(100),
                TextInput::make('montant')->numeric()->minValue(0)->suffix('FCFA')->required(),
                DatePicker::make('date_echeance')->label('Échéance')->required(),
                Toggle::make('actif')->default(true),
            ])->columns(5)->minItems(1)->maxItems(5)->reorderable(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('classe_id')->label('Classe')->formatStateUsing(fn (int $state): string => app(ClasseServiceInterface::class)->getNomClasse($state)),
            TextColumn::make('annee_scolaire_id')->label('Année')->formatStateUsing(fn (int $state): string => app(AnneeScolaireServiceContract::class)->getAnneeScolaire($state)->libelle),
            TextColumn::make('montant_total')->money('XAF')->sortable(),
            TextColumn::make('tranches_count')->counts('tranches')->label('Tranches'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ConfigurationFraisClasseResource\Pages\ListConfigurationsFraisClasse::route('/'),
            'create' => ConfigurationFraisClasseResource\Pages\CreateConfigurationFraisClasse::route('/create'),
            'edit' => ConfigurationFraisClasseResource\Pages\EditConfigurationFraisClasse::route('/{record}/edit'),
        ];
    }
}
