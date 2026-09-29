<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

use App\Models\User;
use App\Modules\Socle\Contracts\HabilitationServiceContract;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ManageUserPermissions extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'socle::filament.pages.manage-user-permissions';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static string|\UnitEnum|null $navigationGroup = 'Utilisateurs & accès';

    protected static ?string $navigationLabel = 'Permissions utilisateurs';

    protected static ?string $title = 'Habilitations dynamiques — Utilisateurs';

    protected static ?int $navigationSort = 17;

    public ?int $userSelectionne = null;

    public ?string $moduleSelectionne = null;

    public ?string $codeEnAttente = null;

    public string $motif = '';

    public function selectionnerModule(string $categorie): void
    {
        $this->moduleSelectionne = $this->moduleSelectionne === $categorie ? null : $categorie;
    }

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasAnyRole(['Fondateur', 'Admin', 'Administrateur', 'Super Admin']) === true
            || $user?->can('gerer_habilitations') === true;
    }

    public function mount(): void
    {
        $demande = request()->query('user');
        $this->userSelectionne = $demande !== null
            ? (int) $demande
            : User::query()->where('statut', 'actif')->orderBy('name')->value('id');
    }

    public function getUsersProperty(): Collection
    {
        return User::query()
            ->where('statut', 'actif')
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Fondateur'))
            ->orderBy('name')
            ->get();
    }

    public function getMatriceProperty(): Collection
    {
        $user = $this->userCourant();

        if (! $user) {
            return collect();
        }

        return app(HabilitationServiceContract::class)->getMatricePourUser($user)->groupBy('categorie');
    }

    public function demanderChangement(string $code, bool $actif): void
    {
        $user = $this->userCourant();
        if (! $user) {
            Notification::make()->warning()->title('Aucun utilisateur sélectionné')->send();

            return;
        }

        if (! $actif) {
            try {
                app(HabilitationServiceContract::class)->accorderModulePourUser($user, $code);
                Notification::make()
                    ->success()
                    ->title('Habilitation accordée avec succès')
                    ->body("L'accès à la fonctionnalité a été accordé spécifiquement à {$user->name}.")
                    ->send();
                $this->dispatch('platform-state-updated');
            } catch (\Throwable $e) {
                Notification::make()
                    ->danger()
                    ->title('Échec de l’octroi')
                    ->body($e->getMessage())
                    ->send();
            }

            return;
        }

        $this->codeEnAttente = $code;
        $this->motif = '';
        $this->dispatch('open-modal', id: 'motif-retrait-user');
    }

    public function confirmerRetrait(): void
    {
        $this->validate(['motif' => ['required', 'string', 'min:3', 'max:1000']]);

        $user = $this->userCourant();
        if (! $user) {
            Notification::make()->warning()->title('Aucun utilisateur sélectionné')->send();

            return;
        }

        try {
            app(HabilitationServiceContract::class)->retirerModulePourUser(
                $user,
                (string) $this->codeEnAttente,
                $this->motif,
            );

            $this->dispatch('close-modal', id: 'motif-retrait-user');
            $this->reset('codeEnAttente', 'motif');

            Notification::make()
                ->success()
                ->title('Habilitation retirée avec succès')
                ->body("L'accès a été retiré spécifiquement pour l'utilisateur {$user->name} et l'action a été consignée dans l'audit.")
                ->send();
            $this->dispatch('platform-state-updated');
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Échec du retrait')
                ->body($e->getMessage())
                ->send();
        }
    }

    private function userCourant(): ?User
    {
        return $this->userSelectionne
            ? User::query()->find($this->userSelectionne)
            : null;
    }
}
