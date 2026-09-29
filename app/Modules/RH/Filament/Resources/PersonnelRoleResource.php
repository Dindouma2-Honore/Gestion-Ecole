<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources;

use App\Modules\RH\Filament\Resources\PersonnelRoleResource\Pages;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class PersonnelRoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'rôle du personnel';

    protected static ?string $pluralModelLabel = 'rôles du personnel';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom du rôle')->required()->maxLength(100)->unique(ignoreRecord: true),
            TextInput::make('guard_name')->label('Contexte')->default('web')->required()->readOnly(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Rôle')->searchable()->sortable(),
            TextColumn::make('users_count')->counts('users')->label('Utilisateurs associés')->sortable(),
            TextColumn::make('created_at')->label('Créé le')->dateTime('d/m/Y')->sortable(),
        ])->actions([
            EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPersonnelRoles::route('/'),
            'create' => Pages\CreatePersonnelRole::route('/create'),
            'edit' => Pages\EditPersonnelRole::route('/{record}/edit'),
        ];
    }
}
