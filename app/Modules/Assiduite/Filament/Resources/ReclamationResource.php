<?php

namespace App\Modules\Assiduite\Filament\Resources;

use App\Modules\Assiduite\Filament\Resources\ReclamationResource\Pages;
use App\Modules\Assiduite\Models\Reclamation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReclamationResource extends Resource
{
    protected static ?string $model = Reclamation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|\UnitEnum|null $navigationGroup = 'Assiduité';

    protected static ?string $modelLabel = 'Incident / Réclamation';

    protected static ?string $pluralModelLabel = 'Incidents & Réclamations';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->options([
                    'reclamation_parent' => 'Réclamation parent',
                    'incident_scolaire' => 'Incident scolaire',
                    'plainte' => 'Plainte',
                ])
                ->required(),
            TextInput::make('categorie')
                ->maxLength(100),
            Textarea::make('description')
                ->required(),
            Select::make('priorite')
                ->options([
                    'basse' => 'Basse',
                    'normale' => 'Normale',
                    'haute' => 'Haute',
                    'urgente' => 'Urgente',
                ])
                ->default('normale')
                ->required(),
            Select::make('responsable_id')
                ->label('Responsable assigné')
                ->relationship('responsable', 'name')
                ->searchable(),
            DatePicker::make('delai_reponse')
                ->label('Délai de réponse'),
            Select::make('statut')
                ->options([
                    'ouverte' => 'Ouverte',
                    'affectee' => 'Affectée',
                    'en_cours' => 'En cours',
                    'resolue' => 'Résolue',
                    'cloturee' => 'Clôturée',
                ])
                ->default('ouverte')
                ->required(),
            Textarea::make('reponse')
                ->label('Réponse / Résolution'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->sortable(),
                TextColumn::make('categorie')->searchable(),
                TextColumn::make('description')->limit(40),
                TextColumn::make('priorite')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'basse' => 'gray',
                        'normale' => 'info',
                        'haute' => 'warning',
                        'urgente' => 'danger',
                        default => 'primary',
                    }),
                TextColumn::make('responsable.name')->label('Responsable'),
                TextColumn::make('delai_reponse')->date()->sortable(),
                TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ouverte' => 'warning',
                        'affectee', 'en_cours' => 'primary',
                        'resolue', 'cloturee' => 'success',
                        default => 'gray',
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReclamations::route('/'),
            'create' => Pages\CreateReclamation::route('/create'),
            'edit' => Pages\EditReclamation::route('/{record}/edit'),
        ];
    }
}
