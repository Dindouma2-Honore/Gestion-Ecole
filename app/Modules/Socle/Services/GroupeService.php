<?php

declare(strict_types=1);

namespace App\Modules\Socle\Services;

use App\Models\User;
use App\Modules\Communication\Contracts\NotificationServiceContract;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\GroupeServiceContract;
use App\Modules\Socle\Exceptions\FonctionnaliteIntrouvableException;
use App\Modules\Socle\Exceptions\MotifRetraitPermissionGroupeRequisException;
use App\Modules\Socle\Models\Fonctionnalite;
use App\Modules\Socle\Models\Groupe;
use App\Modules\Socle\Models\HabilitationGroupeFonctionnalite;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GroupeService implements GroupeServiceContract
{
    public function __construct(
        private readonly AuditServiceContract $audit,
        private readonly NotificationServiceContract $notifications,
    ) {}

    public function creer(array $donnees): object
    {
        $groupe = Groupe::query()->create($donnees);
        $this->audit->enregistrer($groupe, "Création du groupe {$groupe->nom}");

        return $groupe;
    }

    public function modifier(object $groupe, array $donnees): object
    {
        $groupe->update($donnees);
        $this->audit->enregistrer($groupe, "Modification du groupe {$groupe->nom}");

        return $groupe;
    }

    public function ajouterMembres(object $groupe, array $userIds): void
    {
        $groupe->membres()->syncWithoutDetaching($userIds);
        $this->audit->enregistrer($groupe, 'Ajout de membre(s) au groupe '.$groupe->nom);
    }

    public function synchroniserMembres(object $groupe, array $userIds): void
    {
        $avant = $groupe->membres()->pluck('users.id')->map(fn ($id): int => (int) $id)->all();
        $apres = array_values(array_unique(array_map('intval', $userIds)));
        sort($avant);
        sort($apres);

        if ($avant === $apres) {
            return;
        }

        $groupe->membres()->sync($apres);
        $ajoutes = array_values(array_diff($apres, $avant));
        $retires = array_values(array_diff($avant, $apres));

        $this->audit->enregistrer(
            $groupe,
            sprintf(
                'Synchronisation des membres du groupe %s : +%d / -%d',
                $groupe->nom,
                count($ajoutes),
                count($retires),
            ),
        );
    }

    public function retirerMembre(object $groupe, User $user): void
    {
        $groupe->membres()->detach($user->id);
        $this->audit->enregistrer($groupe, "Retrait de {$user->name} du groupe {$groupe->nom}");
    }

    public function accorderModulePourGroupe(object $groupe, string $moduleCode): void
    {
        $this->modifierPermission($groupe, $moduleCode, true, null);
    }

    public function retirerModulePourGroupe(object $groupe, string $moduleCode, string $motif): void
    {
        if (trim($motif) === '') {
            throw new MotifRetraitPermissionGroupeRequisException;
        }

        $this->modifierPermission($groupe, $moduleCode, false, trim($motif));
    }

    public function getMatricePourGroupe(object $groupe): Collection
    {
        $records = HabilitationGroupeFonctionnalite::query()
            ->where('groupe_id', $groupe->id)
            ->get()
            ->keyBy('fonctionnalite_id');

        return Fonctionnalite::query()->where('actif', true)->orderBy('categorie')->orderBy('ordre')->get()
            ->map(function (Fonctionnalite $fonctionnalite) use ($records): Fonctionnalite {
                $record = $records->get($fonctionnalite->id);
                $fonctionnalite->setAttribute('habilitation_active', (bool) ($record->actif ?? false));
                $fonctionnalite->setAttribute('dernier_motif', $record?->dernier_motif);

                return $fonctionnalite;
            });
    }

    private function modifierPermission(object $groupe, string $moduleCode, bool $actif, ?string $motif): void
    {
        $fonctionnalite = Fonctionnalite::query()->where('code', $moduleCode)->first();
        if (! $fonctionnalite) {
            throw new FonctionnaliteIntrouvableException($moduleCode);
        }

        DB::transaction(function () use ($groupe, $fonctionnalite, $actif, $motif): void {
            $habilitation = HabilitationGroupeFonctionnalite::query()->updateOrCreate(
                ['groupe_id' => $groupe->id, 'fonctionnalite_id' => $fonctionnalite->id],
                ['actif' => $actif, 'modifie_par' => Auth::id(), 'dernier_motif' => $motif, 'modifie_le' => now()],
            );

            $description = ($actif ? 'Attribution' : 'Retrait')
                ." de {$fonctionnalite->code} pour le groupe {$groupe->nom}";

            if ($actif) {
                $this->audit->enregistrer($habilitation, $description);
            } else {
                $this->audit->enregistrerAvecMotif($habilitation, $description, (string) $motif);
            }

            $groupe->membres()
                ->where('statut', 'actif')
                ->each(fn (User $user) => $this->notifierChangement($user, $fonctionnalite, $actif, $groupe));
        });
    }

    private function notifierChangement(
        User $user,
        Fonctionnalite $fonctionnalite,
        bool $actif,
        object $groupe,
    ): void {
        $this->notifications->envoyer('in_app', 'habilitation_modifiee', $user, [
            'fonctionnalite' => $fonctionnalite->nom,
            'code' => $fonctionnalite->code,
            'module' => $fonctionnalite->categorie,
            'groupe' => $groupe->nom,
            'action' => $actif ? 'accordee' : 'retiree',
            'date' => now()->toDateTimeString(),
        ]);
    }
}
