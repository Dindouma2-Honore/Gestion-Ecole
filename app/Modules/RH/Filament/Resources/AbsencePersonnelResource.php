<?php

namespace App\Modules\RH\Filament\Resources;

use App\Modules\RH\Models\AbsencePersonnel;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AbsencePersonnelResource extends Resource
{
    protected static ?string $model = AbsencePersonnel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources humaines';

    protected static ?string $modelLabel = 'Absence du personnel';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Select::make('employe_id')->relationship('employe', 'nom')->required()->searchable(), DatePicker::make('date_debut')->required(), DatePicker::make('date_fin')->required()->afterOrEqual('date_debut'), Select::make('type')->options(['absence_justifiee' => 'Absence justifiée', 'absence_non_justifiee' => 'Absence non justifiée', 'conge' => 'Congé', 'permission' => 'Permission'])->required(), Textarea::make('motif')->required(), Select::make('statut')->options(['en_attente' => 'En attente', 'validee' => 'Validée', 'rejetee' => 'Rejetée'])->default('en_attente')->required()]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('employe.nom_complet')->label('Personnel')->searchable(), TextColumn::make('type')->badge(), TextColumn::make('date_debut')->date(), TextColumn::make('date_fin')->date(), TextColumn::make('statut')->badge()]);
    }

    public static function getPages(): array
    {
        return ['index' => AbsencePersonnelResource\Pages\ListAbsencesPersonnel::route('/'), 'create' => AbsencePersonnelResource\Pages\CreateAbsencePersonnel::route('/create'), 'edit' => AbsencePersonnelResource\Pages\EditAbsencePersonnel::route('/{record}/edit')];
    }
}
