<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Filament\Resources;

use App\Filament\Support\RecordModal;
use App\Modules\Assiduite\Filament\Resources\JustificationAssiduiteResource\Pages;
use App\Modules\Assiduite\Models\JustificationAssiduite;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class JustificationAssiduiteResource extends Resource
{
    protected static ?string $model = JustificationAssiduite::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'justification';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Select::make('personne_type')->label('Type de personne')->options(['eleve' => 'Élève', 'personnel' => 'Personnel'])->required()->live(), Select::make('personne_id')->label('Personne')->searchable()->preload()->options(fn ($get) => $get('personne_type') === 'personnel' ? DB::table('employes')->orderBy('nom')->get()->mapWithKeys(fn ($e) => [$e->id => trim("{$e->nom} {$e->prenom}")])->all() : DB::table('eleves')->whereNull('deleted_at')->orderBy('nom')->get()->mapWithKeys(fn ($e) => [$e->id => trim("{$e->nom} {$e->prenom}")." — {$e->matricule_permanent}"])->all())->required(), Select::make('type')->options(['absence' => 'Absence', 'retard' => 'Retard', 'depart_anticipe' => 'Départ anticipé'])->required(), DatePicker::make('date_concernee')->label('Date concernée')->required(), Textarea::make('motif')->required()->columnSpanFull(), Select::make('decision')->options(['en_attente' => 'En attente', 'acceptee' => 'Acceptée', 'refusee' => 'Refusée'])->default('en_attente')->required(), Textarea::make('commentaire_decision')->label('Commentaire de décision')->columnSpanFull()]);
    }

    public static function table(Table $table): Table
    {
        return RecordModal::configure($table->columns([Tables\Columns\TextColumn::make('date_concernee')->label('Date')->date('d/m/Y')->sortable(), Tables\Columns\TextColumn::make('personne_type')->label('Type')->badge(), Tables\Columns\TextColumn::make('type')->badge(), Tables\Columns\TextColumn::make('motif')->limit(45), Tables\Columns\TextColumn::make('decision')->badge()])->actions([EditAction::make()])->defaultSort('soumis_le', 'desc')->defaultPaginationPageOption(5), static::class);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListJustifications::route('/'), 'create' => Pages\CreateJustification::route('/create'), 'edit' => Pages\EditJustification::route('/{record}/edit')];
    }
}
