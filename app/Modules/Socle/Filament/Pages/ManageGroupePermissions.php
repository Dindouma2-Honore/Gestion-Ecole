<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

use App\Models\User;
use App\Modules\Socle\Contracts\GroupeServiceContract;
use App\Modules\Socle\Models\Groupe;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ManageGroupePermissions extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'socle::filament.pages.manage-groupe-permissions';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Utilisateurs & accès';

    protected static ?string $navigationLabel = 'Permissions des groupes';

    protected static ?string $title = 'Habilitations dynamiques — Groupes';

    protected static ?int $navigationSort = 16;

    public ?int $groupeSelectionne = null;

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
        $demande = request()->query('groupe');
        $this->groupeSelectionne = $demande !== null
            ? (int) $demande
            : Groupe::query()->orderBy('nom')->value('id');
    }

    public function getGroupesProperty(): Collection
    {
        return Groupe::query()->orderBy('nom')->get();
    }

    public function getMatriceProperty(): Collection
    {
        $groupe = $this->groupeCourant();

        if (! $groupe) {
            return collect();
        }

        return app(GroupeServiceContract::class)->getMatricePourGroupe($groupe)->groupBy('categorie');
    }

    public function demanderChangement(string $code, bool $actif): void
    {
        $groupe = $this->groupeCourant();
        if (! $groupe) {
            Notification::make()->warning()->title('Aucun groupe sélectionné')->send();

            return;
        }

        if (! $actif) {
            try {
                app(GroupeServiceContract::class)->accorderModulePourGroupe($groupe, $code);
                Notification::make()
                    ->success()
                    ->title('Permission accordée avec succès')
                    ->body("La fonctionnalité a été accordée au groupe {$groupe->nom}.")
                    ->send();
                $this->dispatch('platform-state-updated');
            } catch (\Throwable $e) {
                Notification::make()
                    ->danger()
                    ->title('Échec de l’octroi de permission')
                    ->body($e->getMessage())
                    ->send();
            }

            return;
        }

        $this->codeEnAttente = $code;
        $this->motif = '';
        $this->dispatch('open-modal', id: 'motif-retrait-groupe');
    }

    public function confirmerRetrait(): void
    {
        $this->validate(['motif' => ['required', 'string', 'min:3', 'max:1000']]);

        $groupe = $this->groupeCourant();
        if (! $groupe) {
            Notification::make()->warning()->title('Aucun groupe sélectionné')->send();

            return;
        }

        try {
            app(GroupeServiceContract::class)->retirerModulePourGroupe(
                $groupe,
                (string) $this->codeEnAttente,
                $this->motif,
            );

            $this->dispatch('close-modal', id: 'motif-retrait-groupe');
            $this->reset('codeEnAttente', 'motif');

            Notification::make()
                ->success()
                ->title('Permission retirée avec succès')
                ->body("La permission a été retirée du groupe {$groupe->nom} et l'action a été consignée dans le journal d'audit.")
                ->send();
            $this->dispatch('platform-state-updated');
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Échec du retrait de permission')
                ->body($e->getMessage())
                ->send();
        }
    }

    private function groupeCourant(): ?Groupe
    {
        return $this->groupeSelectionne
            ? Groupe::query()->find($this->groupeSelectionne)
            : null;
    }
}
