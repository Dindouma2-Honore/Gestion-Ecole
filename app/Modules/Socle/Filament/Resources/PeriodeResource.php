<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Modules\Socle\Enums\AcademicPeriodStatus;
use App\Modules\Socle\Models\Periode;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PeriodeResource extends Resource
{
    protected static ?string $model = Periode::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-date-range';

    protected static string|\UnitEnum|null $navigationGroup = 'Années & périodes';

    protected static ?string $modelLabel = 'Période scolaire';

    protected static ?string $pluralModelLabel = 'Périodes scolaires';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('annee_scolaire_id')->label('Année scolaire')->relationship('anneeScolaire', 'libelle')->required()->searchable()->preload(),
            Select::make('parent_id')->label('Période parente')->options(fn (Get $get, ?Periode $record): array => Periode::query()
                ->where('annee_scolaire_id', $get('annee_scolaire_id'))
                ->whereIn('type', ['trimestre', 'semestre'])
                ->when($record, fn ($query) => $query->whereKeyNot($record->id))
                ->orderBy('ordre')->pluck('libelle', 'id')->all())
                ->searchable()->nullable()->helperText('À sélectionner pour rattacher une séquence à un trimestre ou semestre.'),
            TextInput::make('libelle')->label('Libellé')->required()->maxLength(50),
            Select::make('type')->options(['trimestre' => 'Trimestre', 'semestre' => 'Semestre', 'sequence' => 'Séquence'])->required(),
            DatePicker::make('date_debut')->label('Date de début')->required(),
            DatePicker::make('date_fin')->label('Date de fin')->required()->afterOrEqual('date_debut'),
            TextInput::make('ordre')->numeric()->minValue(1)->maxValue(255)->required(),
            Select::make('status')->label('État')->options(['open' => 'Ouverte', 'closed' => 'Clôturée'])->default('open')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('anneeScolaire.libelle')->label('Année')->sortable(),
            TextColumn::make('ordre')->sortable(),
            TextColumn::make('parent.libelle')->label('Période parente')->placeholder('Période principale'),
            TextColumn::make('libelle')->label('Période')->searchable(),
            TextColumn::make('type')->badge(),
            TextColumn::make('date_debut')->date('d/m/Y'),
            TextColumn::make('date_fin')->date('d/m/Y'),
            TextColumn::make('status')->label('État')->badge()->formatStateUsing(fn (AcademicPeriodStatus $state): string => $state === AcademicPeriodStatus::CLOSED ? 'Clôturée' : 'Ouverte'),
        ])->defaultSort('ordre')->recordActions([
            Action::make('cloturer')->label('Clôturer')->icon('heroicon-o-lock-closed')->color('warning')->requiresConfirmation()
                ->visible(fn (Periode $record): bool => $record->status !== AcademicPeriodStatus::CLOSED)
                ->action(fn (Periode $record) => $record->update(['status' => AcademicPeriodStatus::CLOSED])),
            EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => PeriodeResource\Pages\ListPeriodes::route('/'),
            'create' => PeriodeResource\Pages\CreatePeriode::route('/create'),
            'edit' => PeriodeResource\Pages\EditPeriode::route('/{record}/edit'),
        ];
    }
}
