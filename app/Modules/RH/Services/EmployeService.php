<?php

namespace App\Modules\RH\Services;

use App\Models\User;
use App\Modules\RH\Contracts\EmployeServiceContract;
use App\Modules\RH\Exceptions\MatriculeDejaExistantException;
use App\Modules\RH\Models\CategoriePersonnel;
use App\Modules\RH\Models\Employe;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class EmployeService implements EmployeServiceContract
{
    public function creerAcces(int $employeId, string $email, int $roleId): array
    {
        return DB::transaction(function () use ($employeId, $email, $roleId): array {
            $employe = Employe::query()->lockForUpdate()->findOrFail($employeId);
            $role = Role::query()->findOrFail($roleId);
            $email = mb_strtolower(trim($email));

            if ($employe->user_id) {
                $user = User::query()->findOrFail($employe->user_id);
                $user->update(['statut' => 'actif']);
                $user->syncRoles([$role]);

                return ['user' => $user, 'mot_de_passe_temporaire' => null];
            }

            $user = User::query()->where('email', $email)->first();
            $motDePasse = null;
            if ($user && Employe::query()->where('user_id', $user->id)->whereKeyNot($employe->id)->exists()) {
                throw new \DomainException('Cette adresse e-mail est déjà rattachée à un autre membre du personnel.');
            }

            if (! $user) {
                $motDePasse = Str::password(12);
                $user = User::query()->create([
                    'name' => $employe->nom_complet,
                    'nom' => $employe->nom,
                    'prenom' => $employe->prenom,
                    'email' => $email,
                    'telephone' => $employe->telephone,
                    'password' => $motDePasse,
                    'niveau_id' => $employe->niveau_id,
                    'statut' => 'actif',
                    'must_change_password' => true,
                ]);
            } else {
                $user->update(['statut' => 'actif']);
            }

            $user->syncRoles([$role]);
            $employe->update(['user_id' => $user->id, 'email' => $email, 'role_id' => $role->id, 'poste' => $role->name]);

            return ['user' => $user, 'mot_de_passe_temporaire' => $motDePasse];
        });
    }

    public function embaucher(array $donnees): array
    {
        return DB::transaction(function () use ($donnees): array {
            $email = mb_strtolower(trim((string) $donnees['email']));

            if (User::query()->where('email', $email)->exists()) {
                throw new \DomainException('Un compte utilisateur utilise déjà cette adresse e-mail.');
            }

            $motDePasse = Str::password(12);
            $role = Role::query()->findOrFail((int) $donnees['role_id']);
            $ligneSalariale = ! empty($donnees['grille_salariale_id'])
                ? DB::table('grilles_salariales')->where('actif', true)->where('id', $donnees['grille_salariale_id'])->first()
                : null;
            if (! empty($donnees['grille_salariale_id']) && ! $ligneSalariale) {
                throw new \DomainException('La ligne de grille salariale sélectionnée est indisponible.');
            }
            $formule = $this->determinerFormulePaie(
                (bool) ($donnees['responsabilite_fixe'] ?? false),
                (bool) ($donnees['responsabilite_horaire'] ?? false),
            );

            $user = User::query()->create([
                'name' => trim($donnees['nom'].' '.$donnees['prenom']),
                'nom' => $donnees['nom'],
                'prenom' => $donnees['prenom'],
                'email' => $email,
                'telephone' => $donnees['telephone'] ?? null,
                'password' => $motDePasse,
                'niveau_id' => $donnees['niveau_id'] ?? null,
                'statut' => 'actif',
                'must_change_password' => true,
            ]);
            $user->syncRoles([$role]);

            $employe = Employe::query()->create(array_merge(
                Arr::only($donnees, [
                    'nom', 'prenom', 'date_naissance', 'sexe', 'telephone', 'email',
                    'photo', 'role_id', 'poste_administratif_id', 'departement',
                    'date_embauche', 'niveau_id',
                ]),
                [
                    'user_id' => $user->id,
                    'email' => $email,
                    'matricule' => $this->prochainMatricule(),
                    'poste' => $role->name,
                    'statut' => 'actif',
                    'categorie_anciennete_id' => CategoriePersonnel::query()->where('progression_automatique', true)->where('anciennete_min_mois', 0)->value('id'),
                ]
            ));

            $employe->historiqueCarriere()->create([
                'evenement' => 'embauche',
                'nouveau_poste' => $employe->poste,
                'date_evenement' => $employe->date_embauche,
                'commentaire' => 'Embauche et création automatique du compte utilisateur',
            ]);

            $contrat = $employe->contrats()->create([
                'type' => $donnees['type_contrat'],
                'categorie_paie' => $formule,
                'grille_salariale_id' => $ligneSalariale?->id,
                'matiere_paie_id' => $ligneSalariale?->matiere_id,
                'tache_administrative' => $ligneSalariale?->tache,
                'date_debut' => $donnees['date_debut_contrat'],
                'date_fin' => $donnees['date_fin_contrat'] ?? null,
                'periode_essai_fin' => $donnees['periode_essai_fin'] ?? null,
                'salaire_base' => in_array($formule, ['fixe', 'mixte'], true) ? ($ligneSalariale?->salaire_base ?? $donnees['salaire_base'] ?? 0) : 0,
                'taux_horaire' => in_array($formule, ['horaire', 'mixte'], true) ? ($ligneSalariale?->taux_horaire ?? $donnees['taux_horaire'] ?? 0) : 0,
                'statut' => 'actif',
            ]);
            $employe->update(['contrat_id' => $contrat->id]);

            if (! empty($donnees['categories'])) {
                $employe->categories()->sync($donnees['categories']);
            }

            return ['employe' => $employe, 'mot_de_passe_temporaire' => $motDePasse];
        });
    }

    public function creerEmploye(array $donnees): object
    {
        if (Employe::where('matricule', $donnees['matricule'])->exists()) {
            throw new MatriculeDejaExistantException($donnees['matricule']);
        }

        return DB::transaction(function () use ($donnees) {
            $employe = Employe::create($donnees);

            $employe->historiqueCarriere()->create([
                'evenement' => 'embauche',
                'ancien_poste' => null,
                'nouveau_poste' => $employe->poste,
                'date_evenement' => $employe->date_embauche ?? now(),
                'commentaire' => 'Embauche initiale',
            ]);

            return $employe;
        });
    }

    public function muter(int $employeId, string $nouveauPoste, ?int $nouveauNiveauId, string $commentaire): void
    {
        DB::transaction(function () use ($employeId, $nouveauPoste, $nouveauNiveauId, $commentaire) {
            $employe = Employe::findOrFail($employeId);

            $employe->historiqueCarriere()->create([
                'evenement' => 'mutation',
                'ancien_poste' => $employe->poste,
                'nouveau_poste' => $nouveauPoste,
                'date_evenement' => now(),
                'commentaire' => $commentaire,
            ]);

            $employe->update(['poste' => $nouveauPoste, 'niveau_id' => $nouveauNiveauId]);

            if ($employe->user_id) {
                User::where('id', $employe->user_id)->update(['niveau_id' => $nouveauNiveauId]);
            }
        });
    }

    public function promouvoir(int $employeId, string $nouveauPoste, string $commentaire): void
    {
        DB::transaction(function () use ($employeId, $nouveauPoste, $commentaire) {
            $employe = Employe::findOrFail($employeId);

            $employe->historiqueCarriere()->create([
                'evenement' => 'promotion',
                'ancien_poste' => $employe->poste,
                'nouveau_poste' => $nouveauPoste,
                'date_evenement' => now(),
                'commentaire' => $commentaire,
            ]);

            $employe->update(['poste' => $nouveauPoste]);
        });
    }

    public function getEmployeParUser(int $userId): ?object
    {
        return Employe::where('user_id', $userId)->first();
    }

    private function determinerFormulePaie(bool $fixe, bool $horaire): string
    {
        if (! $fixe && ! $horaire) {
            throw new \DomainException('Sélectionnez au moins un type de responsabilité.');
        }

        return $fixe && $horaire ? 'mixte' : ($fixe ? 'fixe' : 'horaire');
    }

    private function prochainMatricule(): string
    {
        $prefixe = 'EMP-'.now()->format('Y').'-';
        $dernier = Employe::query()
            ->where('matricule', 'like', $prefixe.'%')
            ->lockForUpdate()
            ->orderByDesc('matricule')
            ->value('matricule');
        $numero = $dernier ? ((int) Str::afterLast($dernier, '-') + 1) : 1;

        return $prefixe.str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }
}
