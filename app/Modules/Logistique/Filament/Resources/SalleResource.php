<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources;

use App\Filament\Support\NiveauScopeSelect;
use App\Filament\Support\RecordModal;
use App\Modules\Logistique\Filament\Resources\SalleResource\Pages;
use App\Modules\Logistique\Models\Salle;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class SalleResource extends Resource
{
    protected static ?string $model = Salle::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|UnitEnum|null $navigationGroup = 'Établissement & paramètres';

    protected static ?string $navigationLabel = 'Espaces';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'salle';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->label('Code')->unique(ignoreRecord: true)->maxLength(40),
                TextInput::make('nom')->label('Nom de l’espace')->required()->maxLength(255),
                TextInput::make('batiment')->label('Bâtiment'),
                TextInput::make('zone')->label('Zone'),

                Select::make('type')
                    ->label('Type')
                    ->options([
                        'classe' => 'Classe', 'bureau' => 'Bureau',
                        'laboratoire' => 'Laboratoire', 'terrain' => 'Terrain', 'autre' => 'Autre',
                    ])
                    ->default('classe')
                    ->required(),

                TextInput::make('capacite')->label('Capacité')->numeric(),

                NiveauScopeSelect::make(),
                Select::make('responsable_id')->label('Responsable')->searchable()->preload()
                    ->options(fn () => DB::table('employes')->where('statut', 'actif')->orderBy('nom')->get()->mapWithKeys(fn ($e) => [$e->id => trim("{$e->nom} {$e->prenom}")])->all()),

                Select::make('etat')
                    ->label('État')
                    ->options([
                        'bon' => 'Disponible', 'a_renover' => 'En maintenance / travaux', 'hors_service' => 'Hors service',
                    ])
                    ->default('bon')
                    ->required(),
                Toggle::make('disponible')->label('Disponible')->default(true),
                Textarea::make('observations')->label('Observations')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $table = $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Code')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('nom')->label('Espace')->searchable(),
                Tables\Columns\TextColumn::make('batiment')->label('Bâtiment')->placeholder('—'),
                Tables\Columns\TextColumn::make('type')->label('Type')->badge(),
                Tables\Columns\TextColumn::make('capacite')->label('Capacité')->placeholder('—'),
                Tables\Columns\TextColumn::make('etat')
                    ->label('État')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'bon' => 'success',
                        'a_renover' => 'warning',
                        'hors_service' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('etat')->options([
                    'bon' => 'Bon', 'a_renover' => 'À rénover', 'hors_service' => 'Hors service',
                ]),
            ])
            ->actions([
                EditAction::make(),
            ]);

        return RecordModal::configure($table, static::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalles::route('/'),
            'create' => Pages\CreateSalle::route('/create'),
            'edit' => Pages\EditSalle::route('/{record}/edit'),
        ];
    }
}
