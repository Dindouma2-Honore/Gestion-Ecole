<?php

namespace App\Modules\RH\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Filament\Support\RhActions;
use App\Modules\RH\Models\Conge;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\TypeConge;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                ->options(fn (): array => Employe::query()->where('statut', 'actif')->orderBy('nom')->orderBy('prenom')->get()
                    ->mapWithKeys(fn (Employe $employe): array => [$employe->id => $employe->nom_complet])->all())
                ->searchable()
                ->preload()
                ->required(),
            Select::make('type')
                ->label('Type de congé')
                ->options(fn (): array => TypeConge::query()->where('actif', true)->orderBy('nom')->pluck('nom', 'code')->all())
                ->searchable()
                ->preload()
                ->required(),
            DatePicker::make('date_debut')
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::calculerNombreJours($get, $set))
                ->required(),
            DatePicker::make('date_fin')
                ->live()
                ->afterOrEqual('date_debut')
                ->afterStateUpdated(fn (Get $get, Set $set) => self::calculerNombreJours($get, $set))
                ->required(),
            TextInput::make('nombre_jours')
                ->numeric()
                ->label('Nombre de jours ouvrables')
                ->readOnly()
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
        AmbassadorsDesign::configureTable($table, null);

        return $table
            ->columns([
                TextColumn::make('employe.nom_complet')->label('Employé')->sortable()->searchable(),
                TextColumn::make('type')->label('Type de congé')->formatStateUsing(fn (string $state): string => TypeConge::query()->where('code', $state)->value('nom') ?? $state)->sortable(),
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
            ])
            ->recordActions([RhActions::edit()->label('Modifier')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => CongeResource\Pages\ListConges::route('/'),
            'create' => CongeResource\Pages\CreateConge::route('/create'),
            'edit' => CongeResource\Pages\EditConge::route('/{record}/edit'),
        ];
    }

    private static function calculerNombreJours(Get $get, Set $set)
    {
        $debut = $get('date_debut');
        $fin = $get('date_fin');

        if (! filled($debut) || ! filled($fin)) {
            $set('nombre_jours', null);

            return null;
        }

        $parametrage = app(ParametrageServiceContract::class);
        $nombreJours = 0;

        foreach (CarbonPeriod::create($debut, $fin) as $date) {
            if (! $date->isWeekend() && ! $parametrage->estJourFerie($date)) {
                $nombreJours++;
            }
        }

        $set('nombre_jours', $nombreJours);

        return null;
    }
}
