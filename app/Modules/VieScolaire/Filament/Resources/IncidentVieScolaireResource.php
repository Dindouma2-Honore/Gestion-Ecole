<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources;

use App\Filament\Support\RecordModal;
use App\Modules\VieScolaire\Filament\Resources\IncidentVieScolaireResource\Pages;
use App\Modules\VieScolaire\Models\IncidentVieScolaire;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class IncidentVieScolaireResource extends Resource
{
    protected static ?string $model = IncidentVieScolaire::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'incident';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('reference')->label('Référence')->default(fn () => 'INC-'.now()->format('Ymd-His'))->required()->unique(ignoreRecord: true),
            Select::make('categorie')->options(['comportement' => 'Comportement', 'securite' => 'Sécurité', 'violence' => 'Violence', 'degradation' => 'Dégradation', 'accident' => 'Accident', 'transport' => 'Transport', 'harcelement' => 'Harcèlement', 'autre' => 'Autre'])->required(),
            DateTimePicker::make('date_incident')->label('Date et heure')->default(now())->required(), TextInput::make('lieu')->label('Lieu'),
            Textarea::make('description')->required()->columnSpanFull(),
            Select::make('priorite')->options(['basse' => 'Basse', 'normale' => 'Normale', 'haute' => 'Haute', 'urgente' => 'Urgente'])->default('normale')->required(),
            Select::make('responsable_id')->label('Responsable')->searchable()->preload()->options(fn () => DB::table('users')->orderBy('name')->pluck('name', 'id')->all()),
            Select::make('statut')->options(['signale' => 'Signalé', 'en_analyse' => 'En analyse', 'affecte' => 'Affecté', 'en_traitement' => 'En traitement', 'resolu' => 'Résolu', 'cloture' => 'Clôturé', 'rejete' => 'Rejeté', 'annule' => 'Annulé'])->default('signale')->required(),
            Textarea::make('resolution')->label('Résolution')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return RecordModal::configure($table->columns([
            Tables\Columns\TextColumn::make('reference')->searchable(), Tables\Columns\TextColumn::make('categorie')->badge(), Tables\Columns\TextColumn::make('date_incident')->dateTime('d/m/Y H:i')->sortable(), Tables\Columns\TextColumn::make('lieu')->placeholder('—'), Tables\Columns\TextColumn::make('priorite')->badge(), Tables\Columns\TextColumn::make('statut')->badge(),
        ])->actions([EditAction::make(), DeleteAction::make()])->defaultSort('date_incident', 'desc')->defaultPaginationPageOption(5), static::class);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListIncidents::route('/'), 'create' => Pages\CreateIncident::route('/create'), 'edit' => Pages\EditIncident::route('/{record}/edit')];
    }
}
