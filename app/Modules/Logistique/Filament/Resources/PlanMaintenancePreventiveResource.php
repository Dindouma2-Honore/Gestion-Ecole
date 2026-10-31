<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources;

use App\Filament\Support\RecordModal;
use App\Modules\Logistique\Filament\Resources\PlanMaintenancePreventiveResource\Pages;
use App\Modules\Logistique\Models\PlanMaintenancePreventive;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class PlanMaintenancePreventiveResource extends Resource
{
    protected static ?string $model = PlanMaintenancePreventive::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'maintenance préventive';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('equipement_id')->label('Équipement')->searchable()->preload()->options(fn () => DB::table('equipements')->orderBy('nom')->get()->mapWithKeys(fn ($e) => [$e->id => "{$e->nom} — {$e->numero_identification}"])->all())->required(),
            Select::make('responsable_id')->label('Responsable')->searchable()->preload()->options(fn () => DB::table('employes')->where('statut', 'actif')->orderBy('nom')->get()->mapWithKeys(fn ($e) => [$e->id => trim("{$e->nom} {$e->prenom}")])->all()),
            TextInput::make('frequence_jours')->label('Périodicité (jours)')->numeric()->minValue(1)->required(),
            DatePicker::make('derniere_execution')->label('Dernière maintenance'),
            DatePicker::make('prochaine_echeance')->label('Prochaine maintenance')->required(),
            Textarea::make('checklist')->label('Checklist')->columnSpanFull(),
            Toggle::make('actif')->label('Plan actif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return RecordModal::configure($table->columns([
            Tables\Columns\TextColumn::make('equipement_id')->label('Équipement')->formatStateUsing(fn ($state) => optional(DB::table('equipements')->find($state))->nom ?? '—')->searchable(),
            Tables\Columns\TextColumn::make('frequence_jours')->label('Périodicité')->suffix(' jours'),
            Tables\Columns\TextColumn::make('prochaine_echeance')->label('Échéance')->date('d/m/Y')->badge()->color(fn ($record) => $record->prochaine_echeance->isPast() ? 'danger' : ($record->prochaine_echeance->diffInDays(today()) <= 7 ? 'warning' : 'success')),
            Tables\Columns\IconColumn::make('actif')->label('Actif')->boolean(),
        ])->actions([EditAction::make(), DeleteAction::make()])->defaultPaginationPageOption(5), static::class);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPlansMaintenance::route('/'), 'create' => Pages\CreatePlanMaintenance::route('/create'), 'edit' => Pages\EditPlanMaintenance::route('/{record}/edit')];
    }
}
