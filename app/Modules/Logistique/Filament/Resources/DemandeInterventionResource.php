<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources;

use App\Filament\Support\RecordModal;
use App\Modules\Logistique\Filament\Resources\DemandeInterventionResource\Pages;
use App\Modules\Logistique\Models\DemandeIntervention;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class DemandeInterventionResource extends Resource
{
    protected static ?string $model = DemandeIntervention::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|UnitEnum|null $navigationGroup = 'Logistique';

    protected static ?string $navigationLabel = 'Interventions';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'demande d\'intervention';

    /**
     * La vue "Kanban par statut" mentionnée dans la doc H.61 nécessite un
     * plugin Filament dédié (ex: filament-kanban) — à installer selon le
     * choix technique de Joel. Cette Resource fournit en attendant une
     * table standard filtrable par statut, équivalente en fonctionnalité.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('equipement_id')->label('Équipement')->searchable()->preload()->options(fn () => DB::table('equipements')->orderBy('nom')->get()->mapWithKeys(fn ($e) => [$e->id => "{$e->nom} — {$e->numero_identification}"])->all()),
                Select::make('salle_id')->label('Infrastructure')->searchable()->preload()->options(fn () => DB::table('salles')->orderBy('nom')->pluck('nom', 'id')->all()),
                Textarea::make('description')->label('Description')->required()->columnSpanFull(),
                Select::make('type')
                    ->label('Type')
                    ->options(['curative' => 'Curative', 'preventive' => 'Préventive'])
                    ->required(),
                TextInput::make('type_panne')->label('Type de panne'),
                Select::make('priorite')->label('Priorité')->options(['basse' => 'Basse', 'normale' => 'Normale', 'haute' => 'Haute', 'urgente' => 'Urgente'])->default('normale')->required(),
                Select::make('technicien_id')->label('Technicien interne')->searchable()->preload()->options(fn () => DB::table('employes')->where('statut', 'actif')->orderBy('nom')->get()->mapWithKeys(fn ($e) => [$e->id => trim("{$e->nom} {$e->prenom}")])->all()),
                TextInput::make('technicien_externe_nom')->label('Technicien externe'),
                Textarea::make('diagnostic')->label('Diagnostic'),
                TextInput::make('cout')->label('Coût')->numeric()->prefix('FCFA'),
                Select::make('statut')
                    ->label('Statut')
                    ->options([
                        'signalee' => 'Signalée', 'diagnostiquee' => 'Diagnostiquée',
                        'en_reparation' => 'En réparation', 'terminee' => 'Terminée',
                    ])
                    ->default('signalee')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $table = $table
            ->columns([
                Tables\Columns\TextColumn::make('equipement.nom')->label('Équipement')->placeholder('—'),
                Tables\Columns\TextColumn::make('salle.nom')->label('Infrastructure')->placeholder('—'),
                Tables\Columns\TextColumn::make('description')->label('Description')->limit(50),
                Tables\Columns\TextColumn::make('type')->label('Type')->badge(),
                Tables\Columns\TextColumn::make('priorite')->label('Priorité')->badge(),
                Tables\Columns\TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'signalee' => 'danger',
                        'diagnostiquee' => 'warning',
                        'en_reparation' => 'info',
                        'terminee' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('date_signalement')->label('Signalée le')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('statut')->options([
                    'signalee' => 'Signalée', 'diagnostiquee' => 'Diagnostiquée',
                    'en_reparation' => 'En réparation', 'terminee' => 'Terminée',
                ]),
            ])
            ->defaultSort('date_signalement', 'desc')
            ->actions([
                EditAction::make(),
            ]);

        return RecordModal::configure($table, static::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDemandesIntervention::route('/'),
            'create' => Pages\CreateDemandeIntervention::route('/create'),
            'edit' => Pages\EditDemandeIntervention::route('/{record}/edit'),
        ];
    }
}
