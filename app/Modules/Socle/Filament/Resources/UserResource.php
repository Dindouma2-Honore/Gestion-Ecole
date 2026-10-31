<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Filament\Support\AmbassadorsDesign;
use App\Models\User;
use App\Modules\RH\Models\Employe;
use Filament\Actions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Utilisateurs & accès';

    protected static ?string $modelLabel = 'Utilisateur';

    protected static ?string $pluralModelLabel = 'Utilisateurs';

    /** Comptes rattachés à un membre du personnel. */
    protected static bool $shouldRegisterNavigation = true;

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return parent::canCreate();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function (Builder $query): void {
                $query->whereIn('id', DB::table('employes')->whereNotNull('user_id')->select('user_id'))
                    ->orWhere('facilg_legacy_id', 'like', 'utilisateur:%')
                    ->orWhere('email', 'admin@ambassadors.com');
            })
            ->where(function (Builder $query): void {
                $query->whereNull('facilg_legacy_id')
                    ->orWhere('facilg_legacy_id', 'not like', 'personnel-access:%');
            })
            ->where('email', 'not like', '%@ambassadors.local')
            ->where('email', 'not like', '%@facilg.import.local')
            ->where('email', '!=', 'migration.facilg@ambassadors.local');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employe_id')
                    ->label('Membre du personnel')
                    ->options(fn (): array => Employe::query()
                        ->where('statut', 'actif')
                        ->where(function ($query) {
                            $query->whereNull('user_id')
                                ->orWhereHas('user', function ($u) {
                                    $u->where('email', 'like', '%@ambassadors.local')
                                        ->orWhere('email', 'like', '%@facilg.import.local')
                                        ->orWhere('facilg_legacy_id', 'like', 'personnel-access:%')
                                        ->orWhere('statut', 'desactive');
                                });
                        })
                        ->orderBy('nom')
                        ->get()
                        ->mapWithKeys(function (Employe $employe): array {
                            $type = $employe->poste ?: ($employe->departement ?: 'Personnel');

                            return [$employe->id => "{$employe->nom_complet} — {$type} ({$employe->matricule})"];
                        })
                        ->all())
                    ->searchable()
                    ->required()
                    ->visibleOn('create')
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        $employe = Employe::query()->find($state);
                        if (! $employe) {
                            return;
                        }
                        $set('nom', $employe->nom);
                        $set('prenom', $employe->prenom);
                        $set('name', $employe->nom_complet);
                        $set('telephone', $employe->telephone);

                        $existingEmail = $employe->email;
                        if (! empty($existingEmail)
                            && ! str_ends_with($existingEmail, '@example.test')
                            && ! str_ends_with($existingEmail, '@facilg.import.local')
                            && ! str_ends_with($existingEmail, '@ambassadors.local')
                            && str_ends_with($existingEmail, '@ambassadors.com')
                        ) {
                            $set('email', $existingEmail);
                        } else {
                            $slug = Str::slug($employe->nom);
                            $set('email', $slug ? "{$slug}@ambassadors.com" : '');
                        }
                    })
                    ->helperText('Sélectionnez le membre du personnel actif (enseignant ou administratif) sans compte configuré.')
                    ->columnSpanFull(),

                TextInput::make('employe_display')
                    ->label('Personnel rattaché')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(function (?User $record): ?string {
                        if (! $record || ! $record->employe) {
                            return 'Dossier personnel non rattaché';
                        }
                        $emp = $record->employe;
                        $type = $emp->poste ?: ($emp->departement ?: 'Personnel');

                        return "{$emp->nom_complet} — {$type} (Matricule: {$emp->matricule})";
                    })
                    ->hiddenOn('create')
                    ->columnSpanFull(),

                FileUpload::make('photo_profil_path')->hiddenOn('create')
                    ->label('Photo de profil')
                    ->avatar()
                    ->image()
                    ->disk('public')
                    ->directory('avatars')
                    ->columnSpanFull(),

                TextInput::make('name')->hiddenOn('create')
                    ->label('Nom d’affichage')
                    ->required()
                    ->maxLength(255),

                TextInput::make('nom')->hiddenOn('create')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),

                TextInput::make('prenom')->hiddenOn('create')
                    ->label('Prénom')
                    ->required(),

                TextInput::make('email')
                    ->label('Identifiant Email (@ambassadors.com)')
                    ->email()
                    ->placeholder('nom@ambassadors.com')
                    ->helperText('Identifiant de connexion officiel au domaine @ambassadors.com')
                    ->required()
                    ->unique(ignoreRecord: true),

                TextInput::make('telephone')
                    ->label('Téléphone WhatsApp')
                    ->disabledOn('create')
                    ->helperText('Le numéro provient de la fiche du personnel.')
                    ->tel()
                    ->maxLength(20),

                Select::make('statut')
                    ->label('Statut du compte')
                    ->options([
                        'actif' => 'Actif',
                        'suspendu' => 'Suspendu',
                        'desactive' => 'Désactivé',
                    ])
                    ->hiddenOn('create')
                    ->default('actif')
                    ->required(),

                TextInput::make('password')
                    ->label(fn (string $operation): string => $operation === 'create' ? 'Mot de passe' : 'Nouveau mot de passe')
                    ->password()
                    ->revealable()
                    ->helperText(fn (string $operation): string => $operation === 'create'
                        ? 'Entrez un mot de passe ou laissez vide pour une génération automatique.'
                        : 'Laissez vide pour conserver le mot de passe actuel. Utilisez l’icône œil pour contrôler la saisie.')
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state)),

                TextInput::make('password_confirmation')
                    ->label('Confirmer le mot de passe')
                    ->password()
                    ->revealable()
                    ->same('password')
                    ->requiredWith('password')
                    ->dehydrated(false)
                    ->helperText('Retapez le mot de passe à l’identique avec l’icône œil pour vérifier.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AmbassadorsDesign::configureTable($table, 'edit')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nom')->label('Nom')->searchable()->sortable()->weight(FontWeight::SemiBold),
                TextColumn::make('prenom')->label('Prénom')->searchable()->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): string => str_ends_with((string) $state, '@facilg.import.local')
                        || str_ends_with((string) $state, '@ambassadors.local')
                            ? 'Non configuré'
                            : (string) $state),
                TextColumn::make('statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'desactive' ? 'Désactivé' : (AmbassadorsDesign::statutLabels()[$state] ?? ucfirst($state)))
                    ->color(fn (string $state): string => $state === 'desactive' ? 'danger' : AmbassadorsDesign::statutColor($state)),
                TextColumn::make('created_at')->label('Créé le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->emptyStateHeading('Aucun utilisateur')
            ->emptyStateDescription('Créez un compte à partir d’un membre du personnel existant.')
            ->recordActions([
                AmbassadorsDesign::tableActionGroup([
                    AmbassadorsDesign::goldenFormModal(
                        Actions\EditAction::make()
                            ->label('Consulter / Modifier')
                            ->modalHeading(fn (User $record): string => "Compte Utilisateur : {$record->name}")
                            ->modalDescription('Fiche du compte utilisateur rattaché au personnel. Vous pouvez modifier ses accès, changer son mot de passe ou supprimer ce compte.')
                            ->mutateRecordDataUsing(function (array $data, User $record): array {
                                $data['password'] = null;
                                $data['password_confirmation'] = null;

                                if (str_ends_with((string) $record->email, '@ambassadors.local')
                                    || str_ends_with((string) $record->email, '@facilg.import.local')
                                ) {
                                    $slug = Str::slug($record->nom ?: $record->name);
                                    $data['email'] = $slug ? "{$slug}@ambassadors.com" : '';
                                    if ($record->statut === 'desactive') {
                                        $data['statut'] = 'actif';
                                    }
                                }

                                return $data;
                            })
                            ->using(function (User $record, array $data): User {
                                $updateData = [
                                    'name' => trim((string) ($data['name'] ?? '')) ?: trim(($data['prenom'] ?? '').' '.($data['nom'] ?? '')),
                                    'nom' => $data['nom'] ?? $record->nom,
                                    'prenom' => $data['prenom'] ?? $record->prenom,
                                    'email' => mb_strtolower(trim($data['email'])),
                                    'statut' => $data['statut'] ?? $record->statut,
                                ];

                                if (! empty($data['password'])) {
                                    $updateData['password'] = $data['password'];
                                    $updateData['must_change_password'] = false;
                                }

                                if (str_starts_with((string) $record->facilg_legacy_id, 'personnel-access:')) {
                                    $updateData['facilg_legacy_id'] = null;
                                }

                                $record->update($updateData);

                                return $record;
                            })
                            ->extraModalFooterActions(fn (User $record, Actions\EditAction $action): array => [
                                Actions\Action::make('modalDelete')
                                    ->label('Supprimer cet utilisateur')
                                    ->color('danger')
                                    ->icon('heroicon-o-trash')
                                    ->requiresConfirmation()
                                    ->modalHeading('Supprimer ce compte utilisateur ?')
                                    ->modalDescription('Supprimer ce compte ? Le dossier du personnel sera conservé.')
                                    ->visible(fn (): bool => UserResource::canDelete($record))
                                    ->cancelParentActions()
                                    ->action(function () use ($record, $action): void {
                                        $record->delete();

                                        $livewire = $action->getLivewire();
                                        if ($livewire instanceof HasActions) {
                                            $livewire->unmountAction(true);
                                        }
                                        if ($livewire instanceof HasTable) {
                                            $livewire->resetTable();
                                            $livewire->flushCachedTableRecords();
                                        }

                                        Notification::make()
                                            ->success()
                                            ->title('Utilisateur supprimé')
                                            ->body('Le compte utilisateur a été supprimé. Le dossier personnel a été conservé.')
                                            ->send();
                                    }),
                            ])
                    ),
                    Actions\DeleteAction::make()
                        ->label('Supprimer')
                        ->modalDescription('Supprimer ce compte ? Le dossier du personnel sera conservé.'),
                ]),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        return $record->getKey() !== auth()->id() && ! $record->hasRole('Fondateur') && parent::canDelete($record);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('nom')->label('Nom'),
            TextEntry::make('prenom')->label('Prénom'),
            TextEntry::make('email')->label('Identifiant'),
            TextEntry::make('telephone')->label('WhatsApp'),
            TextEntry::make('statut')->label('Statut')->badge(),
            TextEntry::make('created_at')->label('Créé le')->dateTime(),
        ])->columns(2);
    }

    public static function getPages(): array
    {
        return [
            'index' => UserResource\Pages\ListUsers::route('/'),
            'create' => UserResource\Pages\CreateUser::route('/create'),
            'view' => UserResource\Pages\ViewUser::route('/{record}'),
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
        ];
    }
}
