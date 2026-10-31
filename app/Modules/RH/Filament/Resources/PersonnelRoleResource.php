<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Filament\Resources\PersonnelRoleResource\Pages;
use App\Modules\RH\Filament\Support\RhActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
        ]);
    }

    public static function table(Table $table): Table
    {
        AmbassadorsDesign::configureTable($table, null);

        return $table->columns([
            TextColumn::make('name')->label('Rôle')->searchable()->sortable(),
            TextColumn::make('users_count')->counts('users')->label('Utilisateurs associés')->sortable(),
            TextColumn::make('created_at')->label('Créé le')->dateTime('d/m/Y')->sortable(),
        ])->defaultSort('created_at', 'desc')->actions([
            RhActions::edit()
                ->successNotification(fn (Role $record): Notification => Notification::make()
                    ->color('gray')
                    ->title('Rôle mis à jour')
                    ->body('Le rôle « '.$record->name.' » a été mis à jour avec succès.')),
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
