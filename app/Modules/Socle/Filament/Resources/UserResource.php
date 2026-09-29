<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Models\User;
use App\Modules\Socle\Models\Niveau;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Utilisateurs & accès';

    protected static ?string $modelLabel = 'Utilisateur';

    protected static ?string $pluralModelLabel = 'Utilisateurs';

    /** Les comptes du personnel sont créés exclusivement lors de l'embauche dans les RH. */
    protected static bool $shouldRegisterNavigation = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('photo_profil_path')
                    ->label('Photo de profil')
                    ->avatar()
                    ->image()
                    ->disk('public')
                    ->directory('avatars')
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->label('Nom d’affichage')
                    ->required()
                    ->maxLength(255),
                TextInput::make('nom')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),
                TextInput::make('prenom')
                    ->label('Prénom')
                    ->required(),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('telephone')
                    ->label('Téléphone')
                    ->tel()
                    ->maxLength(20),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),
                Select::make('role')
                    ->options(function (?User $record): array {
                        $roles = self::rolesAutorises();
                        if (User::role('Fondateur')->where('statut', 'actif')->when($record, fn ($query) => $query->whereKeyNot($record->id))->exists()) {
                            $roles = array_values(array_diff($roles, ['Fondateur']));
                        }

                        return array_combine($roles, $roles);
                    })
                    ->helperText(fn (?User $record): ?string => User::role('Fondateur')->where('statut', 'actif')->when($record, fn ($query) => $query->whereKeyNot($record->id))->exists()
                        ? 'Le rôle Fondateur est déjà attribué. Utilisez le transfert explicite pour le céder.'
                        : null)
                    ->required()
                    ->live()
                    ->dehydrated(false),
                Select::make('niveau_id')
                    ->label('Niveau d\'enseignement')
                    ->options(Niveau::pluck('nom', 'id'))
                    ->required(fn (Get $get): bool => in_array($get('role'), ['Directeur', 'Enseignant'], true))
                    ->disabled(fn (Get $get): bool => ! in_array($get('role'), ['Directeur', 'Enseignant'], true))
                    ->dehydrated(),
                Select::make('statut')
                    ->options([
                        'actif' => 'Actif',
                        'suspendu' => 'Suspendu',
                        'desactive' => 'Désactivé',
                    ])
                    ->default('actif')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_profil_path')
                    ->label('Photo')
                    ->disk('public')
                    ->visibility('public')
                    ->circular(),
                TextColumn::make('nom')->searchable()->sortable(),
                TextColumn::make('prenom')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('roles.name')->badge()->color('info'),
                TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'actif' => 'success',
                        'suspendu' => 'warning',
                        'desactive' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => UserResource\Pages\ListUsers::route('/'),
            'create' => UserResource\Pages\CreateUser::route('/create'),
            'edit' => UserResource\Pages\EditUser::route('/{record}/edit'),
        ];
    }

    /** @return list<string> */
    public static function rolesAutorises(): array
    {
        return [
            'Fondateur',
            'Directeur',
            'Comptable',
            'SurveillantGeneral',
            'ChargeLogistique',
            'Enseignant',
            'Parent',
            'Eleve',
        ];
    }
}
