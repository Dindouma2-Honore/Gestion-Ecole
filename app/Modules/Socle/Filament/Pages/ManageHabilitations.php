<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

use App\Models\User;
use App\Modules\Socle\Contracts\HabilitationServiceContract;
use App\Modules\Socle\Services\HabilitationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ManageHabilitations extends Page
{
    protected string $view = 'socle::filament.pages.manage-habilitations';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'Utilisateurs & accès';

    protected static ?string $navigationLabel = 'Habilitations dynamiques';

    protected static ?string $title = 'Habilitations dynamiques';

    protected static ?int $navigationSort = 15;

    public string $roleSelectionne = 'Directeur';

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

    public function getRoles(): array
    {
        return HabilitationService::getRolesAdministrables();
    }

    public function getMatriceProperty(): Collection
    {
        return app(HabilitationServiceContract::class)
            ->getMatricePourRole($this->roleSelectionne)
            ->groupBy('categorie');
    }

    public function demanderChangement(string $code, bool $actif): void
    {
        if (! $actif) {
            try {
                app(HabilitationServiceContract::class)->activerModulePourRole($this->roleSelectionne, $code);
                Notification::make()
                    ->success()
                    ->title('Habilitation activée avec succès')
                    ->body("L'accès à la fonctionnalité a été accordé au rôle {$this->roleSelectionne}.")
                    ->send();
                $this->dispatch('platform-state-updated');
            } catch (\Throwable $e) {
                Notification::make()
                    ->danger()
                    ->title('Échec de l’activation')
                    ->body($e->getMessage())
                    ->send();
            }

            return;
        }

        $this->codeEnAttente = $code;
        $this->motif = '';
        $this->dispatch('open-modal', id: 'motif-desactivation');
    }

    public function confirmerDesactivation(): void
    {
        $this->validate(['motif' => ['required', 'string', 'min:3', 'max:1000']]);

        try {
            app(HabilitationServiceContract::class)->desactiverModulePourRole(
                $this->roleSelectionne,
                (string) $this->codeEnAttente,
                $this->motif,
            );

            $this->dispatch('close-modal', id: 'motif-desactivation');
            $this->reset('codeEnAttente', 'motif');

            Notification::make()
                ->success()
                ->title('Habilitation désactivée avec succès')
                ->body("L'accès a été désactivé pour le rôle {$this->roleSelectionne} et consigné dans le journal d'audit.")
                ->send();
            $this->dispatch('platform-state-updated');
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Échec de la désactivation')
                ->body($e->getMessage())
                ->send();
        }
    }
}
