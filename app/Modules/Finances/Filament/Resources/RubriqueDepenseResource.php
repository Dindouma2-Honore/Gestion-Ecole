<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources;

use App\Models\User;
use App\Modules\Finances\Filament\Resources\RubriqueDepenseResource\Pages;
use App\Modules\Finances\Models\RubriqueDepense;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RubriqueDepenseResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = RubriqueDepense::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?string $modelLabel = 'Rubrique de dépense';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')->required()->maxLength(120),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('nom')->searchable()->sortable(),
            IconColumn::make('active')->boolean(),
            TextColumn::make('depenses_count')->counts('depenses')->label('Dépenses'),
        ]);
    }

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('Fondateur') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRubriquesDepense::route('/'),
            'create' => Pages\CreateRubriqueDepense::route('/create'),
        ];
    }
}
