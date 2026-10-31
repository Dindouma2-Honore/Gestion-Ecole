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

    protected static ?string $navigationLabel = 'Groupes';

    protected static ?string $title = 'Habilitations des groupes';

    protected static ?int $navigationSort = 16;

    public function getSubheading(): ?string
    {
        return 'Matrice des habilitations dynamiques accordées aux groupes d’utilisateurs.';
    }

    public ?int $groupeSelectionne = null;

    public ?string $moduleSelectionne = null;

    public ?string $codeEnAttente = null;

    public string $motif = '';

    // Propriétés pour la création d'un groupe via modale
    public string $nouveauGroupeNom = '';

    public string $nouveauGroupeDescription = '';

    public array $nouveauxMembresIds = [];

    // Propriétés pour la gestion des membres et infos du groupe via modale
    public string $groupeNom = '';

    public string $groupeDescription = '';

    public ?int $userAAjouterId = null;

    public string $rechercheMembre = '';

    public function selectionnerModule(string $categorie): void
    {
        $this->moduleSelectionne = $this->moduleSelectionne === $categorie ? null : $categorie;
    }

    public function selectionnerGroupe(int $groupeId): void
    {
        $this->groupeSelectionne = $groupeId;
    }

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasAnyRole(['Fondateur', 'Admin', 'Administrateur', 'Super Admin']) === true;
    }

    public function mount(): void
    {
        if (Groupe::query()->count() === 0) {
            $service = app(GroupeServiceContract::class);
            $g1 = $service->creer(['nom' => 'Direction pédagogique', 'description' => 'Coordination des programmes, enseignants et évaluations']);
            $g2 = $service->creer(['nom' => 'Comité financier', 'description' => 'Gestion financière, caisse et contrôle des règlements']);
            $g3 = $service->creer(['nom' => 'Vie scolaire & discipline', 'description' => 'Surveillance générale, assiduité et événements scolaires']);
            $g4 = $service->creer(['nom' => 'Commission des admissions', 'description' => 'Inscriptions, transfert et gestion des dossiers élèves']);
            $admin = User::query()->where('statut', 'actif')->first();
            if ($admin) {
                $service->ajouterMembres($g1, [$admin->id]);
                $service->ajouterMembres($g2, [$admin->id]);
            }
        }

        $demande = request()->query('groupe');
        $this->groupeSelectionne = $demande !== null
            ? (int) $demande
            : Groupe::query()->orderBy('nom')->value('id');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getCategorieInfo(string $categorie): array
    {
        $map = [
            'Administration' => [
                'icon' => 'heroicon-o-cog-6-tooth',
                'description' => 'Socle, sécurité, configuration & audit',
            ],
            'RH' => [
                'icon' => 'heroicon-o-user-group',
                'description' => 'Personnel, contrats, congés & paie',
            ],
            'Ressources humaines' => [
                'icon' => 'heroicon-o-user-group',
                'description' => 'Personnel, contrats, congés & paie',
            ],
            'Scolarité' => [
                'icon' => 'heroicon-o-academic-cap',
                'description' => 'Inscriptions, classes, élèves & réinscriptions',
            ],
            'Pédagogie' => [
                'icon' => 'heroicon-o-book-open',
                'description' => 'Enseignements, notes, bulletins & emplois du temps',
            ],
            'Finances' => [
                'icon' => 'heroicon-o-banknotes',
                'description' => 'Facturation, règlements, caisse & comptabilité',
            ],
            'Logistique' => [
                'icon' => 'heroicon-o-truck',
                'description' => 'Cantine, transport, dortoirs, infirmerie & stocks',
            ],
            'Vie scolaire' => [
                'icon' => 'heroicon-o-heart',
                'description' => 'Discipline, sanctions, conseils & événements',
            ],
            'Assiduité' => [
                'icon' => 'heroicon-o-clock',
                'description' => 'Présences, retards, pointages & justificatifs',
            ],
            'Technique' => [
                'icon' => 'heroicon-o-wrench-screwdriver',
                'description' => 'Maintenance, sauvegardes, journal système & API',
            ],
            'Communication' => [
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'description' => 'Messagerie, SMS, notifications & annonces',
            ],
            'Rapports' => [
                'icon' => 'heroicon-o-document-chart-bar',
                'description' => 'Statistiques, exports & analyses transversales',
            ],
        ];

        return $map[$categorie] ?? [
            'icon' => 'heroicon-o-cube',
            'description' => 'Fonctionnalités et habilitations associées',
        ];
    }

    public function toutAccorderPourModule(): void
    {
        $groupe = $this->groupeCourant();
        $categorie = $this->moduleSelectionne ?? $this->matrice->keys()->first();
        if (! $groupe || ! $categorie) {
            return;
        }

        $features = $this->matrice->get($categorie, collect());
        $service = app(GroupeServiceContract::class);

        foreach ($features as $f) {
            if (! $f->habilitation_active) {
                $service->accorderModulePourGroupe($groupe, $f->code);
            }
        }

        Notification::make()
            ->success()
            ->title('Habilitations accordées')
            ->body("Toutes les fonctionnalités du module {$categorie} sont désormais accordées au groupe {$groupe->nom}.")
            ->send();

        $this->dispatch('platform-state-updated');
    }

    public function demanderToutRestreindre(): void
    {
        $categorie = $this->moduleSelectionne ?? $this->matrice->keys()->first();
        $this->codeEnAttente = 'tout:'.$categorie;
        $this->motif = '';
        $this->dispatch('open-modal', id: 'motif-retrait-groupe');
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

    public function getGroupeCourantProperty(): ?Groupe
    {
        return $this->groupeCourant();
    }

    public function getTousLesUtilisateursProperty(): Collection
    {
        return User::query()
            ->where('statut', 'actif')
            ->orderBy('name')
            ->get();
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
            if (str_starts_with((string) $this->codeEnAttente, 'tout:')) {
                $categorie = substr((string) $this->codeEnAttente, 5);
                $features = $this->matrice->get($categorie, collect());
                foreach ($features as $f) {
                    if ($f->habilitation_active) {
                        app(GroupeServiceContract::class)->retirerModulePourGroupe($groupe, $f->code, $this->motif);
                    }
                }
                $message = "Toutes les fonctionnalités du module {$categorie} ont été retirées pour le groupe {$groupe->nom}.";
            } else {
                app(GroupeServiceContract::class)->retirerModulePourGroupe(
                    $groupe,
                    (string) $this->codeEnAttente,
                    $this->motif,
                );
                $message = "La permission a été retirée du groupe {$groupe->nom} et l'action a été consignée dans le journal d'audit.";
            }

            $this->dispatch('close-modal', id: 'motif-retrait-groupe');
            $this->reset('codeEnAttente', 'motif');

            Notification::make()
                ->success()
                ->title('Permission retirée avec succès')
                ->body($message)
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

    public function ouvrirModalCreerGroupe(): void
    {
        $this->reset('nouveauGroupeNom', 'nouveauGroupeDescription', 'nouveauxMembresIds');
        $this->dispatch('open-modal', id: 'modal-creer-groupe');
    }

    public function creerGroupe(): void
    {
        $this->validate([
            'nouveauGroupeNom' => ['required', 'string', 'min:2', 'max:100', 'unique:groupes,nom'],
            'nouveauGroupeDescription' => ['nullable', 'string', 'max:500'],
        ]);

        $service = app(GroupeServiceContract::class);
        $groupe = $service->creer([
            'nom' => trim($this->nouveauGroupeNom),
            'description' => trim($this->nouveauGroupeDescription) ?: null,
        ]);

        if (! empty($this->nouveauxMembresIds)) {
            $service->ajouterMembres($groupe, array_map('intval', $this->nouveauxMembresIds));
        }

        $this->groupeSelectionne = $groupe->id;
        $this->reset('nouveauGroupeNom', 'nouveauGroupeDescription', 'nouveauxMembresIds');
        $this->dispatch('close-modal', id: 'modal-creer-groupe');

        Notification::make()
            ->success()
            ->title('Groupe créé avec succès')
            ->body("Le groupe {$groupe->nom} a été créé avec succès.")
            ->send();

        $this->dispatch('platform-state-updated');
    }

    public function ouvrirModalMembres(): void
    {
        $groupe = $this->groupeCourant();
        $this->groupeNom = $groupe?->nom ?? '';
        $this->groupeDescription = $groupe?->description ?? '';
        $this->userAAjouterId = null;
        $this->rechercheMembre = '';
        $this->dispatch('open-modal', id: 'modal-membres-groupe');
    }

    public function mettreAJourGroupeInfos(): void
    {
        $groupe = $this->groupeCourant();
        if (! $groupe) {
            return;
        }

        $this->validate([
            'groupeNom' => ['required', 'string', 'min:2', 'max:100', 'unique:groupes,nom,'.$groupe->id],
            'groupeDescription' => ['nullable', 'string', 'max:500'],
        ]);

        app(GroupeServiceContract::class)->modifier($groupe, [
            'nom' => trim($this->groupeNom),
            'description' => trim($this->groupeDescription) ?: null,
        ]);

        Notification::make()
            ->success()
            ->title('Groupe mis à jour')
            ->body('Les informations du groupe ont été enregistrées avec succès.')
            ->send();

        $this->dispatch('platform-state-updated');
    }

    public function ajouterMembreAuGroupe(?int $userId = null): void
    {
        $id = $userId ?? $this->userAAjouterId;
        if (! $id) {
            return;
        }

        $groupe = $this->groupeCourant();
        if (! $groupe) {
            return;
        }

        app(GroupeServiceContract::class)->ajouterMembres($groupe, [$id]);
        $this->userAAjouterId = null;

        Notification::make()
            ->success()
            ->title('Membre ajouté')
            ->body("L'utilisateur a été rattaché au groupe {$groupe->nom}.")
            ->send();

        $this->dispatch('platform-state-updated');
    }

    public function retirerMembreDuGroupe(int $userId): void
    {
        $groupe = $this->groupeCourant();
        $user = User::query()->find($userId);

        if (! $groupe || ! $user) {
            return;
        }

        app(GroupeServiceContract::class)->retirerMembre($groupe, $user);

        Notification::make()
            ->success()
            ->title('Membre retiré')
            ->body("{$user->name} a été retiré(e) du groupe {$groupe->nom}.")
            ->send();

        $this->dispatch('platform-state-updated');
    }

    private function groupeCourant(): ?Groupe
    {
        if ($this->groupeSelectionne) {
            $groupe = Groupe::query()->find($this->groupeSelectionne);
            if ($groupe) {
                return $groupe;
            }
        }

        $premier = Groupe::query()->orderBy('nom')->first();
        if ($premier) {
            $this->groupeSelectionne = $premier->id;

            return $premier;
        }

        return null;
    }
}
