<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

use App\Models\User;
use App\Modules\Socle\Contracts\HabilitationServiceContract;
use App\Modules\Socle\Models\Fonctionnalite;
use App\Modules\Socle\Models\HabilitationRoleFonctionnalite;
use App\Modules\Socle\Services\HabilitationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class ManageHabilitations extends Page
{
    protected string $view = 'socle::filament.pages.manage-habilitations';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'Utilisateurs & accès';

    protected static ?string $navigationLabel = 'Habilitation du personnel';

    protected static ?string $title = 'Habilitation du personnel';

    protected static ?int $navigationSort = 15;

    public function getSubheading(): ?string
    {
        return 'Matrice des habilitations dynamiques par habilitation et profil du personnel.';
    }

    public string $roleSelectionne = 'Directeur';

    public string $moduleSelectionne = 'Administration';

    public ?string $codeEnAttente = null;

    public string $motif = '';

    public bool $afficherSousFonctionnalites = false;

    public const MODULES = [
        'Administration' => [
            'key' => 'socle',
            'code_module' => 'module.socle',
            'nom' => 'Administration',
            'description' => 'Socle, sécurité, configuration & audit',
            'icon' => 'heroicon-o-cog-6-tooth',
            'categorie' => 'Administration',
        ],
        'Ressources humaines' => [
            'key' => 'rh',
            'code_module' => 'module.rh',
            'nom' => 'Ressources humaines',
            'description' => 'Personnel, contrats, congés & paie',
            'icon' => 'heroicon-o-user-group',
            'categorie' => 'RH',
        ],
        'Scolarité' => [
            'key' => 'scolarite',
            'code_module' => 'module.scolarite',
            'nom' => 'Scolarité',
            'description' => 'Élèves, inscriptions, classes & scolarité',
            'icon' => 'heroicon-o-academic-cap',
            'categorie' => 'Scolarité',
        ],
        'Pédagogie' => [
            'key' => 'pedagogie',
            'code_module' => 'module.pedagogie',
            'nom' => 'Pédagogie',
            'description' => 'Enseignements, programmes, emplois du temps & bulletins',
            'icon' => 'heroicon-o-book-open',
            'categorie' => 'Pédagogie',
        ],
        'Communication' => [
            'key' => 'communication',
            'code_module' => 'module.communication',
            'nom' => 'Communication',
            'description' => 'Annonces, rendez-vous & notifications',
            'icon' => 'heroicon-o-chat-bubble-left-right',
            'categorie' => 'Communication',
        ],
        'Finances' => [
            'key' => 'finances',
            'code_module' => 'module.finances',
            'nom' => 'Finances',
            'description' => 'Caisse, paiements, dépenses & états comptables',
            'icon' => 'heroicon-o-banknotes',
            'categorie' => 'Finances',
        ],
        'Assiduité' => [
            'key' => 'assiduite',
            'code_module' => 'module.assiduite',
            'nom' => 'Assiduité',
            'description' => 'Présences, absences, retards & justifications',
            'icon' => 'heroicon-o-clock',
            'categorie' => 'Assiduité',
        ],
        'Vie scolaire' => [
            'key' => 'viescolaire',
            'code_module' => 'module.viescolaire',
            'nom' => 'Vie scolaire',
            'description' => 'Événements, discipline, santé & visites',
            'icon' => 'heroicon-o-building-library',
            'categorie' => 'Vie scolaire',
        ],
        'Logistique' => [
            'key' => 'logistique',
            'code_module' => 'module.logistique',
            'nom' => 'Logistique',
            'description' => 'Salles, équipements, transport, cantine & bibliothèque',
            'icon' => 'heroicon-o-truck',
            'categorie' => 'Logistique',
        ],
        'Technique' => [
            'key' => 'technique',
            'code_module' => 'module.technique',
            'nom' => 'Technique',
            'description' => 'Maintenance, parc informatique & support',
            'icon' => 'heroicon-o-wrench-screwdriver',
            'categorie' => 'Technique',
        ],
    ];

    public const OPERATIONS = [
        'consulter' => [
            'key' => 'consulter',
            'nom' => 'Consulter',
            'description' => 'Lecture seule, consultation des fiches, listes, tableaux de bord et historiques.',
            'icon' => 'heroicon-o-eye',
            'color' => 'blue',
        ],
        'modifier' => [
            'key' => 'modifier',
            'nom' => 'Modifier',
            'description' => 'Création, mise à jour, modification et enregistrement des données du module.',
            'icon' => 'heroicon-o-pencil-square',
            'color' => 'amber',
        ],
        'executer' => [
            'key' => 'executer',
            'nom' => 'Exécuter',
            'description' => 'Traitements métier, validations, clôtures et déclenchement d\'actions réservées.',
            'icon' => 'heroicon-o-bolt',
            'color' => 'purple',
        ],
        'imprimer' => [
            'key' => 'imprimer',
            'nom' => 'Imprimer',
            'description' => 'Impression de documents officiels, attestations et exports PDF / Excel.',
            'icon' => 'heroicon-o-printer',
            'color' => 'emerald',
        ],
    ];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasAnyRole(['Fondateur', 'Admin', 'Administrateur', 'Super Admin']) === true;
    }

    public function selectionnerRole(string $role): void
    {
        $this->roleSelectionne = $role;
    }

    public function selectionnerModule(string $moduleNomOuCle): void
    {
        foreach (self::MODULES as $nom => $config) {
            if ($nom === $moduleNomOuCle || $config['key'] === $moduleNomOuCle) {
                $this->moduleSelectionne = $nom;

                return;
            }
        }
        $this->moduleSelectionne = $moduleNomOuCle;
    }

    public function getRoles(): array
    {
        return HabilitationService::getRolesAdministrables();
    }

    public function getRoleStats(): array
    {
        $role = $this->roleSelectionne;
        $roleModel = Role::query()->where('name', $role)->first();
        $isStaffDefault = in_array($role, HabilitationService::ROLES_STAFF, true);

        $activeCount = 0;
        if ($roleModel) {
            $pivots = HabilitationRoleFonctionnalite::query()
                ->where('role_id', $roleModel->id)
                ->join('catalogue_fonctionnalites', 'catalogue_fonctionnalites.id', '=', 'habilitations_roles_fonctionnalites.fonctionnalite_id')
                ->pluck('habilitations_roles_fonctionnalites.actif', 'catalogue_fonctionnalites.code')
                ->all();

            foreach (self::MODULES as $mod) {
                foreach (array_keys(self::OPERATIONS) as $op) {
                    $code = "{$mod['key']}.{$op}";
                    $actif = isset($pivots[$code]) ? (bool) $pivots[$code] : $isStaffDefault;
                    if ($actif) {
                        $activeCount++;
                    }
                }
            }
        } else {
            $activeCount = $isStaffDefault ? 40 : 0;
        }

        return [
            'role' => $role,
            'operations_actives' => $activeCount,
            'total_operations' => 40,
            'total_modules' => count(self::MODULES),
        ];
    }

    public function getModulesSummary(): array
    {
        $role = $this->roleSelectionne;
        $isStaffDefault = in_array($role, HabilitationService::ROLES_STAFF, true);

        $roleModel = Role::query()->where('name', $role)->first();
        $pivots = [];
        if ($roleModel) {
            $pivots = HabilitationRoleFonctionnalite::query()
                ->where('role_id', $roleModel->id)
                ->join('catalogue_fonctionnalites', 'catalogue_fonctionnalites.id', '=', 'habilitations_roles_fonctionnalites.fonctionnalite_id')
                ->pluck('habilitations_roles_fonctionnalites.actif', 'catalogue_fonctionnalites.code')
                ->all();
        }

        $result = [];
        foreach (self::MODULES as $nom => $config) {
            $activeOps = 0;
            foreach (array_keys(self::OPERATIONS) as $op) {
                $code = "{$config['key']}.{$op}";
                $actif = isset($pivots[$code]) ? (bool) $pivots[$code] : $isStaffDefault;
                if ($actif) {
                    $activeOps++;
                }
            }

            $result[$nom] = [
                ...$config,
                'operations_actives' => $activeOps,
                'total_operations' => count(self::OPERATIONS),
                'is_selected' => ($this->moduleSelectionne === $nom),
            ];
        }

        return $result;
    }

    public function getModuleSelectionneConfig(): array
    {
        return self::MODULES[$this->moduleSelectionne] ?? self::MODULES['Administration'];
    }

    public function getOperationsPourModuleSelectionne(): array
    {
        $config = $this->getModuleSelectionneConfig();
        $service = app(HabilitationServiceContract::class);
        $role = $this->roleSelectionne;
        $operations = [];

        $roleModel = Role::query()->where('name', $role)->first();

        foreach (self::OPERATIONS as $opKey => $opMeta) {
            $code = "{$config['key']}.{$opKey}";
            try {
                $actif = $service->moduleActifPourRole($role, $code);
            } catch (\Throwable) {
                $actif = in_array($role, HabilitationService::ROLES_STAFF, true);
            }

            $dernierMotif = null;
            if ($roleModel) {
                $fonctionnalite = Fonctionnalite::query()->where('code', $code)->first();
                if ($fonctionnalite) {
                    $pivot = HabilitationRoleFonctionnalite::query()
                        ->where('role_id', $roleModel->id)
                        ->where('fonctionnalite_id', $fonctionnalite->id)
                        ->first();
                    $dernierMotif = $pivot?->dernier_motif;
                }
            }

            $operations[] = [
                'key' => $opKey,
                'code' => $code,
                'nom' => $opMeta['nom'],
                'description' => $opMeta['description'],
                'icon' => $opMeta['icon'],
                'color' => $opMeta['color'],
                'actif' => $actif,
                'dernier_motif' => $dernierMotif,
            ];
        }

        return $operations;
    }

    public function getSousFonctionnalitesPourModuleSelectionne(): Collection
    {
        $config = $this->getModuleSelectionneConfig();
        $categorie = $config['categorie'];
        $standardCodes = array_map(fn ($op) => "{$config['key']}.{$op}", array_keys(self::OPERATIONS));
        $standardCodes[] = $config['code_module'];

        return app(HabilitationServiceContract::class)
            ->getMatricePourRole($this->roleSelectionne)
            ->where('categorie', $categorie)
            ->reject(fn ($f) => in_array($f->code, $standardCodes, true))
            ->values();
    }

    public function getMatriceProperty(): Collection
    {
        return app(HabilitationServiceContract::class)
            ->getMatricePourRole($this->roleSelectionne)
            ->groupBy('categorie');
    }

    public function toutAutoriserPourModule(): void
    {
        $config = $this->getModuleSelectionneConfig();
        $service = app(HabilitationServiceContract::class);

        foreach (array_keys(self::OPERATIONS) as $op) {
            $code = "{$config['key']}.{$op}";
            try {
                $service->activerModulePourRole($this->roleSelectionne, $code);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        Notification::make()
            ->success()
            ->title('Opérations autorisées')
            ->body("Toutes les opérations du module {$this->moduleSelectionne} sont désormais autorisées pour l'habilitation {$this->roleSelectionne}.")
            ->send();

        $this->dispatch('platform-state-updated');
    }

    public function demanderToutRestreindre(): void
    {
        $config = $this->getModuleSelectionneConfig();
        $this->codeEnAttente = 'tout:'.$config['key'];
        $this->motif = '';
        $this->dispatch('open-modal', id: 'motif-desactivation');
    }

    public function demanderChangement(string $code, bool $actif): void
    {
        if (! $actif) {
            try {
                app(HabilitationServiceContract::class)->activerModulePourRole($this->roleSelectionne, $code);
                Notification::make()
                    ->success()
                    ->title('Habilitation accordée avec succès')
                    ->body("L'opération a été autorisée pour l'habilitation {$this->roleSelectionne}.")
                    ->send();
                $this->dispatch('platform-state-updated');
            } catch (\Throwable $e) {
                Notification::make()
                    ->danger()
                    ->title("Échec de l'activation")
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
            $service = app(HabilitationServiceContract::class);

            if (str_starts_with((string) $this->codeEnAttente, 'tout:')) {
                $key = substr((string) $this->codeEnAttente, 5);
                foreach (array_keys(self::OPERATIONS) as $op) {
                    $code = "{$key}.{$op}";
                    $service->desactiverModulePourRole(
                        $this->roleSelectionne,
                        $code,
                        $this->motif,
                    );
                }

                $message = "Toutes les opérations du module ont été restreintes pour l'habilitation {$this->roleSelectionne}.";
            } else {
                $service->desactiverModulePourRole(
                    $this->roleSelectionne,
                    (string) $this->codeEnAttente,
                    $this->motif,
                );

                $message = "L'opération a été restreinte pour l'habilitation {$this->roleSelectionne} et consignée dans le journal d'audit.";
            }

            $this->dispatch('close-modal', id: 'motif-desactivation');
            $this->reset('codeEnAttente', 'motif');

            Notification::make()
                ->success()
                ->title('Habilitation restreinte avec succès')
                ->body($message)
                ->send();
            $this->dispatch('platform-state-updated');
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Échec de la restriction')
                ->body($e->getMessage())
                ->send();
        }
    }
}
