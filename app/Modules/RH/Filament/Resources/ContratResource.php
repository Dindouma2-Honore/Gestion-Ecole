<?php

namespace App\Modules\RH\Filament\Resources;

use App\Modules\RH\Models\Contrat;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ContratResource extends Resource
{
    protected static ?string $model = Contrat::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources humaines';

    protected static ?string $modelLabel = 'Contrat de travail';

    protected static ?string $pluralModelLabel = 'Contrats de travail';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employe_id')
                ->relationship('employe', 'nom')
                ->required(),
            Select::make('type')
                ->options([
                    'CDI' => 'CDI (Durée Indéterminée)',
                    'CDD' => 'CDD (Durée Déterminée)',
                    'Stage' => 'Stage',
                    'Prestataire' => 'Prestataire / Vacataire',
                ])
                ->required(),
            Select::make('categorie_paie')
                ->label('Formule selon les responsabilités')
                ->options([
                    'fixe' => 'Salaire de base + primes (maternelle, primaire, secondaire, auxiliaire, autres staff)',
                    'horaire' => 'Taux horaire × heures (autres responsabilités)',
                    'mixte' => 'Cumul des deux formules',
                ])
                ->default('fixe')->live()->required(),
            DatePicker::make('date_debut')
                ->required(),
            DatePicker::make('date_fin'),
            DatePicker::make('periode_essai_fin'),
            TextInput::make('salaire_base')
                ->label('Salaire de base personnel')
                ->numeric()
                ->prefix('FCFA')
                ->visible(fn (Get $get): bool => in_array($get('categorie_paie'), ['fixe', 'mixte'], true))
                ->required(fn (Get $get): bool => in_array($get('categorie_paie'), ['fixe', 'mixte'], true)),
            TextInput::make('taux_horaire')
                ->label('Taux horaire')
                ->numeric()->minValue(0)->prefix('FCFA')
                ->visible(fn (Get $get): bool => in_array($get('categorie_paie'), ['horaire', 'mixte'], true))
                ->required(fn (Get $get): bool => in_array($get('categorie_paie'), ['horaire', 'mixte'], true)),
            Select::make('statut')
                ->options([
                    'actif' => 'Actif',
                    'suspendu' => 'Suspendu',
                    'expire' => 'Expiré',
                    'resilie' => 'Résilié',
                ])
                ->default('actif')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employe.nom_complet')->label('Employé')->sortable()->searchable(),
                TextColumn::make('type')->sortable(),
                TextColumn::make('categorie_paie')->label('Paie')->badge(),
                TextColumn::make('date_debut')->date()->sortable(),
                TextColumn::make('date_fin')->date()->sortable(),
                TextColumn::make('salaire_base')->money('XAF')->sortable(),
                TextColumn::make('taux_horaire')->label('Taux horaire')->money('XAF')->sortable(),
                TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'actif' => 'success',
                        'suspendu' => 'warning',
                        'expire', 'resilie' => 'danger',
                        default => 'gray',
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ContratResource\Pages\ListContrats::route('/'),
            'create' => ContratResource\Pages\CreateContrat::route('/create'),
            'edit' => ContratResource\Pages\EditContrat::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAnyRole(['Fondateur', 'Comptable']) ?? false;
    }
}
