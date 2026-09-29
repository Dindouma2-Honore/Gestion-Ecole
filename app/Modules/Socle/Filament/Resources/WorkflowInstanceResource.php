<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Modules\Socle\Filament\Resources\WorkflowInstanceResource\Pages;
use App\Modules\Socle\Models\WorkflowInstance;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WorkflowInstanceResource extends Resource
{
    protected static ?string $model = WorkflowInstance::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Workflows & validations';

    protected static ?string $navigationLabel = 'Mes validations en attente';

    protected static ?string $pluralModelLabel = 'Validations en attente';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $roles = $user?->getRoleNames()->all() ?? [];

        return parent::getEloquentQuery()
            ->where('statut', 'en_cours')
            ->whereHas('etapeCourante', fn (Builder $query): Builder => $query->where(function (Builder $q) use ($user, $roles): void {
                $q->where(fn (Builder $founder): Builder => $founder->where('validateur_type', 'fondateur')->when(! in_array('Fondateur', $roles, true), fn (Builder $x): Builder => $x->whereRaw('1 = 0')))
                    ->orWhere(fn (Builder $role): Builder => $role->where('validateur_type', 'role')->whereIn('validateur_valeur', $roles))
                    ->orWhere(fn (Builder $specific): Builder => $specific->where('validateur_type', 'utilisateur')->where('validateur_valeur', (string) $user?->id));
            }));
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('module_source')->label('Module')->badge(),
            TextColumn::make('entite_type')->label('Objet')->formatStateUsing(fn (string $state): string => class_basename($state)),
            TextColumn::make('entite_id')->label('N°'),
            TextColumn::make('etapeCourante.nom')->label('Étape'),
            TextColumn::make('created_at')->label('Soumis le')->dateTime('d/m/Y H:i')->sortable(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListWorkflowInstances::route('/')];
    }
}
