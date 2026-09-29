<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Models\User;
use App\Modules\Communication\Contracts\NotificationServiceContract;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\HabilitationServiceContract;
use App\Modules\Socle\Exceptions\FonctionnaliteIntrouvableException;
use App\Modules\Socle\Exceptions\MotifDesactivationHabilitationRequisException;
use App\Modules\Socle\Exceptions\RoleHabilitationInvalideException;
use App\Modules\Socle\Models\Fonctionnalite;
use App\Modules\Socle\Models\HabilitationGroupeFonctionnalite;
use App\Modules\Socle\Models\HabilitationRoleFonctionnalite;
use App\Modules\Socle\Models\HabilitationUserFonctionnalite;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class HabilitationService implements HabilitationServiceContract
{
    public const ROLES_STAFF = ['Directeur', 'Enseignant', 'Comptable', 'SurveillantGeneral', 'ChargeLogistique'];

    public function __construct(
        private readonly AuditServiceContract $audit,
        private readonly NotificationServiceContract $notifications,
    ) {}

    public static function getRolesAdministrables(): array
    {
        $dbRoles = Role::query()
            ->whereNotIn('name', ['Fondateur'])
            ->pluck('name')
            ->toArray();

        if (empty($dbRoles)) {
            return self::ROLES_STAFF;
        }

        return array_values(array_unique(array_merge(self::ROLES_STAFF, $dbRoles)));
    }

    public function moduleActifPourRole(string $role, string $moduleCode): bool
    {
        if ($role === 'Fondateur') {
            return true;
        }

        $fonctionnalite = $this->fonctionnaliteActive($moduleCode);

        $roleModel = $this->roleAdministrable($role);
        $habilitation = HabilitationRoleFonctionnalite::query()
            ->where('role_id', $roleModel->id)
            ->where('fonctionnalite_id', $fonctionnalite->id)
            ->first();

        if ($habilitation !== null) {
            return (bool) $habilitation->actif;
        }

        return in_array($role, self::ROLES_STAFF, true);
    }

    public function moduleActifPourUser(User $user, string $moduleCode): bool
    {
        if ($user->hasRole('Fondateur')) {
            return true;
        }

        $fonctionnalite = $this->fonctionnaliteActive($moduleCode);

        // Priorité aux habilitations spécifiques utilisateur
        $userHabilitation = HabilitationUserFonctionnalite::query()
            ->where('user_id', $user->id)
            ->where('fonctionnalite_id', $fonctionnalite->id)
            ->first();

        if ($userHabilitation !== null) {
            return (bool) $userHabilitation->actif;
        }

        $userRoles = $user->getRoleNames()->reject(fn (string $role): bool => $role === 'Fondateur');
        if ($userRoles->contains(fn (string $role): bool => $this->moduleActifPourRole($role, $moduleCode))) {
            return true;
        }

        // Les permissions accordées via un groupe s'ajoutent aux permissions
        // individuelles/rôle de l'utilisateur : elles ne peuvent jamais en
        // retirer, seulement en octroyer de nouvelles.
        return $this->groupeAccordeFonctionnalite($user, $fonctionnalite);
    }

    public function accorderModulePourUser(User $user, string $moduleCode): void
    {
        $this->modifierUser($user, $moduleCode, true, null);
    }

    public function retirerModulePourUser(User $user, string $moduleCode, string $motif): void
    {
        if (trim($motif) === '') {
            throw new MotifDesactivationHabilitationRequisException;
        }

        $this->modifierUser($user, $moduleCode, false, trim($motif));
    }

    public function getMatricePourUser(User $user): Collection
    {
        $userRecords = HabilitationUserFonctionnalite::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('fonctionnalite_id');

        return Fonctionnalite::query()->where('actif', true)->orderBy('categorie')->orderBy('ordre')->get()
            ->map(function (Fonctionnalite $fonctionnalite) use ($user, $userRecords): Fonctionnalite {
                $record = $userRecords->get($fonctionnalite->id);
                if ($record !== null) {
                    $active = (bool) $record->actif;
                } else {
                    $active = $this->moduleActifPourUser($user, $fonctionnalite->code);
                }
                $fonctionnalite->setAttribute('habilitation_active', $active);
                $fonctionnalite->setAttribute('est_specifique_user', $record !== null);
                $fonctionnalite->setAttribute('dernier_motif', $record?->dernier_motif);

                return $fonctionnalite;
            });
    }

    public function accesAccordeParGroupe(User $user, string $fonctionnaliteCode): bool
    {
        return $this->groupeAccordeFonctionnalite(
            $user,
            $this->fonctionnaliteActive($fonctionnaliteCode),
        );
    }

    public function activerModulePourRole(string $role, string $moduleCode): void
    {
        $this->modifier($role, $moduleCode, true, null);
    }

    public function desactiverModulePourRole(string $role, string $moduleCode, string $motif): void
    {
        if (trim($motif) === '') {
            throw new MotifDesactivationHabilitationRequisException;
        }

        $this->modifier($role, $moduleCode, false, trim($motif));
    }

    public function getMatriceComplete(): Collection
    {
        $roles = self::getRolesAdministrables();

        return collect($roles)->mapWithKeys(fn (string $role): array => [$role => $this->getMatricePourRole($role)]);
    }

    public function getMatricePourRole(string $role): Collection
    {
        $roleModel = $this->roleAdministrable($role);
        $records = HabilitationRoleFonctionnalite::query()
            ->where('role_id', $roleModel->id)
            ->get()
            ->keyBy('fonctionnalite_id');

        return Fonctionnalite::query()->where('actif', true)->orderBy('categorie')->orderBy('ordre')->get()
            ->map(function (Fonctionnalite $fonctionnalite) use ($records): Fonctionnalite {
                $record = $records->get($fonctionnalite->id);
                $fonctionnalite->setAttribute('habilitation_active', (bool) ($record->actif ?? true));
                $fonctionnalite->setAttribute('dernier_motif', $record?->dernier_motif);

                return $fonctionnalite;
            });
    }

    public function getModulesActifsPourUser(User $user): Collection
    {
        $catalogue = Fonctionnalite::query()->where('actif', true)->orderBy('ordre')->get();

        if ($user->hasRole('Fondateur')) {
            return $catalogue;
        }

        return $catalogue->filter(fn (Fonctionnalite $item): bool => $this->moduleActifPourUser($user, $item->code))->values();
    }

    public function getCatalogueParCategorie(): Collection
    {
        return Fonctionnalite::query()->where('actif', true)->orderBy('categorie')->orderBy('ordre')->get()->groupBy('categorie');
    }

    public function synchroniserCatalogue(array $fonctionnalites): void
    {
        DB::transaction(function () use ($fonctionnalites): void {
            foreach ($fonctionnalites as $donnees) {
                Fonctionnalite::query()->updateOrCreate(['code' => $donnees['code']], $donnees);
            }

            $roleNames = self::getRolesAdministrables();
            $roles = Role::query()->whereIn('name', $roleNames)->get();
            $modules = Fonctionnalite::query()->get();
            foreach ($roles as $role) {
                foreach ($modules as $module) {
                    HabilitationRoleFonctionnalite::query()->firstOrCreate(
                        ['role_id' => $role->id, 'fonctionnalite_id' => $module->id],
                        ['actif' => true],
                    );
                }
            }
        });
    }

    private function modifier(string $role, string $moduleCode, bool $actif, ?string $motif): void
    {
        $roleModel = $this->roleAdministrable($role);
        $fonctionnalite = Fonctionnalite::query()->where('code', $moduleCode)->first();
        if (! $fonctionnalite) {
            throw new FonctionnaliteIntrouvableException($moduleCode);
        }

        DB::transaction(function () use ($roleModel, $fonctionnalite, $actif, $motif): void {
            $habilitation = HabilitationRoleFonctionnalite::query()->updateOrCreate(
                ['role_id' => $roleModel->id, 'fonctionnalite_id' => $fonctionnalite->id],
                ['actif' => $actif, 'modifie_par' => Auth::id(), 'dernier_motif' => $motif, 'modifie_le' => now()],
            );

            $description = ($actif ? 'Activation' : 'Désactivation')
                ." de {$fonctionnalite->code} pour le rôle {$roleModel->name}";
            $motifAudit = $actif ? 'Réactivation décidée par le Fondateur' : (string) $motif;
            $this->audit->enregistrerAvecMotif($habilitation, $description, $motifAudit);

            User::role($roleModel->name)
                ->where('statut', 'actif')
                ->each(fn (User $user) => $this->notifierChangement($user, $fonctionnalite, $actif));
        });
    }

    private function modifierUser(User $user, string $moduleCode, bool $actif, ?string $motif): void
    {
        $fonctionnalite = $this->fonctionnaliteActive($moduleCode);

        DB::transaction(function () use ($user, $fonctionnalite, $actif, $motif): void {
            $habilitation = HabilitationUserFonctionnalite::query()->updateOrCreate(
                ['user_id' => $user->id, 'fonctionnalite_id' => $fonctionnalite->id],
                ['actif' => $actif, 'modifie_par' => Auth::id(), 'dernier_motif' => $motif, 'modifie_le' => now()],
            );

            $description = ($actif ? 'Activation' : 'Désactivation')
                ." de {$fonctionnalite->code} pour l'utilisateur {$user->name}";
            $motifAudit = $actif ? "Accès accordé à l'utilisateur" : (string) $motif;
            $this->audit->enregistrerAvecMotif($habilitation, $description, $motifAudit);

            if ($user->statut === 'actif') {
                $this->notifierChangement($user, $fonctionnalite, $actif);
            }
        });
    }

    private function notifierChangement(User $user, Fonctionnalite $fonctionnalite, bool $actif): void
    {
        $this->notifications->envoyer('in_app', 'habilitation_modifiee', $user, [
            'fonctionnalite' => $fonctionnalite->nom,
            'code' => $fonctionnalite->code,
            'module' => $fonctionnalite->categorie,
            'action' => $actif ? 'accordee' : 'retiree',
            'date' => now()->toDateTimeString(),
        ]);
    }

    private function fonctionnaliteActive(string $moduleCode): Fonctionnalite
    {
        $fonctionnalite = Fonctionnalite::query()->where('code', $moduleCode)->where('actif', true)->first();

        return $fonctionnalite ?? throw new FonctionnaliteIntrouvableException($moduleCode);
    }

    private function groupeAccordeFonctionnalite(User $user, Fonctionnalite $fonctionnalite): bool
    {
        $groupeIds = $user->groupes()->pluck('groupes.id');
        if ($groupeIds->isEmpty()) {
            return false;
        }

        // Un utilisateur peut appartenir à plusieurs groupes. Les droits sont
        // fusionnés par union : une seule attribution active suffit, et un
        // refus dans un autre groupe ne peut jamais retirer ce droit.
        return HabilitationGroupeFonctionnalite::query()
            ->whereIn('groupe_id', $groupeIds)
            ->where('fonctionnalite_id', $fonctionnalite->id)
            ->where('actif', true)
            ->exists();
    }

    private function roleAdministrable(string $role): Role
    {
        if ($role === 'Fondateur') {
            throw new RoleHabilitationInvalideException($role);
        }

        $roleModel = Role::query()->where('name', $role)->first();

        return $roleModel ?? throw new RoleHabilitationInvalideException($role);
    }
}
