<?php

namespace App\Modules\RH\Filament\Resources;

use App\Modules\RH\Models\Conge;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CongeResource extends Resource
{
    protected static ?string $model = Conge::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources humaines';

    protected static ?string $modelLabel = 'Congé & Absence';

    protected static ?string $pluralModelLabel = 'Gestion des Congés';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employe_id')
                ->relationship('employe', 'nom')
                ->required(),
            Select::make('type')
                ->options([
                    'conge_annuel' => 'Congé Annuel',
                    'conge_maladie' => 'Congé Maladie',
                    'maternite' => 'Congé Maternité / Paternité',
                    'evenement_familial' => 'Événement Familial',
                    'sans_solde' => 'Congé Sans Solde',
                ])
                ->required(),
            DatePicker::make('date_debut')
                ->required(),
            DatePicker::make('date_fin')
                ->required(),
            TextInput::make('nombre_jours')
                ->numeric()
                ->required(),
            Textarea::make('motif'),
            Select::make('statut')
                ->options([
                    'demande' => 'Demandé',
                    'approuve' => 'Approuvé',
                    'en_cours' => 'En cours',
                    'termine' => 'Terminé',
                    'rejete' => 'Rejeté',
                    'annule' => 'Annulé',
                ])
                ->default('demande')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employe.nom_complet')->label('Employé')->sortable()->searchable(),
                TextColumn::make('type')->sortable(),
                TextColumn::make('date_debut')->date()->sortable(),
                TextColumn::make('date_fin')->date()->sortable(),
                TextColumn::make('nombre_jours')->sortable(),
                TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approuve', 'en_cours', 'termine' => 'success',
                        'demande' => 'warning',
                        'rejete', 'annule' => 'danger',
                        default => 'gray',
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => CongeResource\Pages\ListConges::route('/'),
            'create' => CongeResource\Pages\CreateConge::route('/create'),
            'edit' => CongeResource\Pages\EditConge::route('/{record}/edit'),
        ];
    }
}
