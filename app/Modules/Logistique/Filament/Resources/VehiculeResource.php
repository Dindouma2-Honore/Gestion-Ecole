<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources;

use App\Filament\Support\RecordModal;
use App\Modules\Logistique\Filament\Resources\VehiculeResource\Pages;
use App\Modules\Logistique\Models\Vehicule;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class VehiculeResource extends Resource
{
    protected static ?string $model = Vehicule::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'véhicule';

    public static function form(Schema $schema): Schema
    {
        $personnels = fn () => DB::table('employes')->where('statut', 'actif')->orderBy('nom')->get()->mapWithKeys(fn ($e) => [$e->id => trim("{$e->nom} {$e->prenom}")])->all();

        return $schema->components([
            TextInput::make('code')->label('Code')->unique(ignoreRecord: true),
            TextInput::make('immatriculation')->label('Immatriculation')->required()->unique(ignoreRecord: true),
            TextInput::make('capacite')->label('Capacité')->numeric()->minValue(1)->required(),
            Select::make('etat')->label('État')->options(['bon' => 'Disponible', 'en_maintenance' => 'En maintenance', 'hors_service' => 'Hors service'])->default('bon')->required(),
            Select::make('chauffeur_id')->label('Chauffeur')->searchable()->preload()->options($personnels),
            Select::make('accompagnateur_id')->label('Accompagnateur')->searchable()->preload()->options($personnels),
            Textarea::make('observations')->label('Observations')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return RecordModal::configure($table->columns([
            Tables\Columns\TextColumn::make('code')->label('Code')->placeholder('—')->searchable(),
            Tables\Columns\TextColumn::make('immatriculation')->label('Immatriculation')->searchable(),
            Tables\Columns\TextColumn::make('capacite')->label('Places'),
            Tables\Columns\TextColumn::make('etat')->label('État')->badge()->color(fn ($state) => match ($state) {
                'bon' => 'success', 'en_maintenance' => 'warning', default => 'danger'
            }),
        ])->actions([EditAction::make(), DeleteAction::make()])->defaultPaginationPageOption(5), static::class);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListVehicules::route('/'), 'create' => Pages\CreateVehicule::route('/create'), 'edit' => Pages\EditVehicule::route('/{record}/edit')];
    }
}
