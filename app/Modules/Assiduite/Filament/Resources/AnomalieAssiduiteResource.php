<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Filament\Resources;

use App\Filament\Support\RecordModal;
use App\Modules\Assiduite\Filament\Resources\AnomalieAssiduiteResource\Pages;
use App\Modules\Assiduite\Models\AnomalieAssiduite;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AnomalieAssiduiteResource extends Resource
{
    protected static ?string $model = AnomalieAssiduite::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'anomalie d’assiduité';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Select::make('priorite')->options(['basse' => 'Basse', 'normale' => 'Normale', 'haute' => 'Haute', 'urgente' => 'Urgente'])->required(), Select::make('responsable_id')->label('Responsable')->relationship(name: 'responsable', titleAttribute: 'name')->searchable()->preload(), Select::make('statut')->options(['detectee' => 'Détectée', 'a_verifier' => 'À vérifier', 'corrigee' => 'Corrigée', 'cloturee' => 'Clôturée', 'ignoree' => 'Ignorée'])->required(), Textarea::make('resolution')->label('Résolution / justification')->required(fn ($get) => in_array($get('statut'), ['corrigee', 'cloturee', 'ignoree'], true))->columnSpanFull()]);
    }

    public static function table(Table $table): Table
    {
        return RecordModal::configure($table->columns([Tables\Columns\TextColumn::make('date_anomalie')->label('Date')->date('d/m/Y')->sortable(), Tables\Columns\TextColumn::make('type')->label('Anomalie')->searchable(), Tables\Columns\TextColumn::make('personne_type')->label('Personne')->badge(), Tables\Columns\TextColumn::make('description')->limit(55), Tables\Columns\TextColumn::make('priorite')->badge(), Tables\Columns\TextColumn::make('statut')->badge()])->filters([SelectFilter::make('statut')->options(['detectee' => 'Détectée', 'a_verifier' => 'À vérifier', 'corrigee' => 'Corrigée', 'cloturee' => 'Clôturée', 'ignoree' => 'Ignorée'])])->actions([EditAction::make()->mutateDataUsing(fn (array $data): array => Pages\EditAnomalie::prepareModalData($data))])->defaultSort('date_anomalie', 'desc')->defaultPaginationPageOption(5), static::class);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAnomalies::route('/'), 'edit' => Pages\EditAnomalie::route('/{record}/edit')];
    }
}
