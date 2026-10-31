<?php

namespace App\Modules\Pedagogie\Filament\Support;

use App\Filament\Support\AmbassadorsDesign;
use App\Filament\Support\ModuleAccess;
use App\Filament\Support\RecordModal;
use App\Modules\Pedagogie\Services\OrganisationPedagogiqueContext;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

abstract class OrganisationResource extends Resource
{
    protected static bool $isDiscovered = false;

    public static function context(): OrganisationPedagogiqueContext
    {
        return app(OrganisationPedagogiqueContext::class);
    }

    public static function canViewAny(): bool
    {
        return ModuleAccess::canAccessComponent(auth()->user(), static::class);
    }

    public static function canCreate(): bool
    {
        return static::context()->canManage(static::class);
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    abstract public static function enregistrer(array $data, ?Model $record = null): Model;

    public static function recordData(array $data, Model $record): array
    {
        return $data;
    }

    protected static function organisationTable(Table $table): Table
    {
        return RecordModal::configure(AmbassadorsDesign::configureTable($table, null)->recordActions([
            ViewAction::make()->mutateRecordDataUsing(fn (array $data, Model $record) => static::recordData($data, $record)),
            EditAction::make()->mutateRecordDataUsing(fn (array $data, Model $record) => static::recordData($data, $record))
                ->using(fn (Model $record, array $data) => static::enregistrer($data, $record)),
        ]), static::class)->emptyStateHeading('Aucun élément pour ce contexte')->emptyStateDescription('Ajustez les filtres ou créez un nouvel élément.');
    }

    public static function yearField(): Select
    {
        return Select::make('annee_scolaire_id')->label('Année scolaire')->options(fn () => collect(static::context()->annees())->pluck('libelle', 'id')->all())
            ->default(fn () => static::context()->anneeCourante())->required()->live()->disabled(fn (?Model $record) => $record?->exists ?? false)->dehydrated();
    }

    protected static function yearFilter(): SelectFilter
    {
        return SelectFilter::make('annee_scolaire_id')->label('Année scolaire')->options(fn () => collect(static::context()->annees())->pluck('libelle', 'id')->all())->default(fn () => static::context()->anneeCourante());
    }
}
