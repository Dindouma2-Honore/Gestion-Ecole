<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources;

use App\Models\User;
use App\Modules\Finances\Filament\Resources\MouvementDiversResource\Pages;
use App\Modules\Finances\Models\MouvementCaisse;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class MouvementDiversResource extends Resource
{
    protected static ?string $model = MouvementCaisse::class;

    protected static ?string $slug = 'finances/mouvements-divers';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-up-down';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 7;

    public static function getNavigationLabel(): string
    {
        return __('finance_misc.title');
    }

    public static function getModelLabel(): string
    {
        return __('finance_misc.movement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('finance_misc.title');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->label(__('finance_misc.type'))->options(['encaissement' => __('finance_misc.income'), 'decaissement' => __('finance_misc.expense')])->required()->native(false),
            TextInput::make('montant')->label(__('finance_misc.amount'))->numeric()->minValue(1)->suffix('FCFA')->required(),
            Textarea::make('motif')->label(__('finance_misc.reason'))->required()->minLength(3)->maxLength(1000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->label(__('finance_misc.date'))->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('type')->label(__('finance_misc.type'))->badge()->formatStateUsing(fn (string $state): string => $state === 'encaissement' ? __('finance_misc.income') : __('finance_misc.expense'))->color(fn (string $state): string => $state === 'encaissement' ? 'success' : 'danger'),
            TextColumn::make('montant')->label(__('finance_misc.amount'))->money('XAF')->sortable(),
            TextColumn::make('justificatif')->label(__('finance_misc.reason'))->wrap()->searchable(),
            TextColumn::make('session.date_session')->label(__('finance_misc.cash_session'))->date('d/m/Y'),
        ])->filters([SelectFilter::make('type')->options(['encaissement' => __('finance_misc.income'), 'decaissement' => __('finance_misc.expense')])])->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('module_origine', 'Finances')->where('sous_module', 'Mouvements divers');
    }

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasAnyRole(['Fondateur', 'Comptable']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListMouvementsDivers::route('/'), 'create' => Pages\CreateMouvementDivers::route('/create')];
    }
}
