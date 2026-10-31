<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

use App\Models\User;
use App\Modules\Socle\Enums\AuthorizationAction;
use App\Modules\Socle\Models\AuthorizationGrant;
use App\Modules\Socle\Services\AuthorizationGrantService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Administration minimale des grants Authorization V1
 * (READ / WRITE / EXECUTE / PRINT) — distincte des permissions Spatie
 * et des habilitations legacy gérées par ManageHabilitations et consorts.
 */
class ManageAuthorizationGrants extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'socle::filament.pages.manage-authorization-grants';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'Utilisateurs & accès';

    protected static ?string $navigationLabel = 'Autorisations granulaires';

    protected static ?string $title = 'Autorisations granulaires (Authorization V1)';

    protected static ?int $navigationSort = 18;

    public ?array $data = [];

    public function getSubheading(): ?string
    {
        return 'Grants Authorization V1 — actions READ / WRITE / EXECUTE / PRINT. Indépendant des permissions Spatie et des habilitations legacy.';
    }

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasAnyRole(['Fondateur', 'Admin', 'Administrateur', 'Super Admin']) === true;
    }

    public function mount(): void
    {
        $this->form->fill([
            'type_sujet' => 'user',
            'actions' => [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Enregistrer un grant')
                ->description('Sujet (utilisateur, groupe ou poste RH) + fonctionnalité du catalogue + actions Authorization V1.')
                ->schema([
                    Select::make('type_sujet')
                        ->label('Type de sujet')
                        ->options(fn (): array => $this->service()->typesSujets())
                        ->default('user')
                        ->required()
                        ->live(),
                    Select::make('sujet_id')
                        ->label('Sujet')
                        ->options(fn (Get $get): array => $this->service()->optionsSujets((string) ($get('type_sujet') ?? 'user')))
                        ->searchable()
                        ->required(),
                    Select::make('feature_code')
                        ->label('Fonctionnalité (catalogue)')
                        ->options(fn (): array => $this->service()->optionsFonctionnalites())
                        ->searchable()
                        ->required(),
                    CheckboxList::make('actions')
                        ->label('Actions Authorization V1')
                        ->options([
                            'read' => 'READ — Consulter',
                            'write' => 'WRITE — Modifier',
                            'execute' => 'EXECUTE — Exécuter',
                            'print' => 'PRINT — Imprimer',
                        ])
                        ->columns(2)
                        ->required(),
                ])->columns(2),
        ])->statePath('data');
    }

    public function enregistrer(): void
    {
        $data = $this->form->getState();

        try {
            $this->service()->enregistrer(
                (string) $data['type_sujet'],
                (int) $data['sujet_id'],
                (string) $data['feature_code'],
                (array) $data['actions'],
            );
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('Grant non enregistré')
                ->body(implode(' ', $exception->errors()))
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Grant enregistré')
            ->body('Le grant Authorization V1 a été enregistré.')
            ->send();

        $this->form->fill([
            'type_sujet' => 'user',
            'actions' => [],
        ]);
    }

    public function modifier(int $grantId): void
    {
        try {
            $grant = $this->service()->trouver($grantId);
        } catch (\Throwable) {
            Notification::make()->warning()->title('Grant introuvable')->send();

            return;
        }

        $this->form->fill([
            'type_sujet' => $grant->subject_type->value,
            'sujet_id' => $grant->subject_id,
            'feature_code' => $grant->feature?->code,
            'actions' => array_map(
                static fn (AuthorizationAction $action): string => $action->value,
                $grant->grantedActions(),
            ),
        ]);
    }

    public function basculerEtat(int $grantId): void
    {
        try {
            $grant = $this->service()->trouver($grantId);
        } catch (\Throwable) {
            Notification::make()->warning()->title('Grant introuvable')->send();

            return;
        }

        if ($grant->is_active) {
            $this->service()->desactiver($grantId);
            Notification::make()->success()->title('Grant désactivé')->body('La ligne est conservée pour l’historique.')->send();
        } else {
            $this->service()->reactiver($grantId);
            Notification::make()->success()->title('Grant réactivé')->send();
        }
    }

    /** @return Collection<int, AuthorizationGrant> */
    public function grants()
    {
        return $this->service()->lister();
    }

    private function service(): AuthorizationGrantService
    {
        return app(AuthorizationGrantService::class);
    }
}
