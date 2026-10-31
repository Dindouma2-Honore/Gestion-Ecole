<?php

namespace App\Modules\RH\Services;

use App\Models\User;
use App\Modules\RH\Contracts\EmployeServiceContract;
use App\Modules\RH\Exceptions\MatriculeDejaExistantException;
use App\Modules\RH\Models\Candidature;
use App\Modules\RH\Models\ContratFonctionRemuneration;
use App\Modules\RH\Models\DiplomePersonnel;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\PalierAnciennete;
use App\Modules\RH\Models\PosteAdministratif;
use App\Modules\Socle\Contracts\UtilisateurServiceInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class EmployeService implements EmployeServiceContract
{
    private UtilisateurServiceInterface $utilisateurService;

    public function __construct(?UtilisateurServiceInterface $utilisateurService = null)
    {
        $this->utilisateurService = $utilisateurService ?? app(UtilisateurServiceInterface::class);
    }

    public function creerAcces(int $employeId, string $email, int $roleId): array
    {
        return DB::transaction(function () use ($employeId, $email, $roleId): array {
            $employe = Employe::query()->lockForUpdate()->findOrFail($employeId);
            $role = Role::query()->findOrFail($roleId);
            $email = mb_strtolower(trim($email));

            if ($employe->user_id) {
                $user = User::query()->findOrFail($employe->user_id);
                $email = $email !== '' ? $email : (string) $user->email;

                if ($email === '') {
                    throw new \DomainException('Veuillez renseigner une adresse e-mail valide avant d’activer l’accès système.');
                }

                $this->utilisateurService->reactiverCompte($employe->user_id, $email);
                $this->utilisateurService->synchroniserRoles($employe->user_id, $role->id);

                $libellePoste = $employe->posteAdministratif?->nom ?? $role->name;
                $employe->update(['email' => $email, 'role_id' => $role->id, 'poste' => $libellePoste]);

                return ['user' => $user, 'mot_de_passe_temporaire' => null];
            }

            if ($email === '') {
                throw new \DomainException('Veuillez renseigner une adresse e-mail valide avant d’activer l’accès système.');
            }

            $user = User::query()->where('email', $email)->first();
            $motDePasse = null;
            if ($user && Employe::query()->where('user_id', $user->id)->whereKeyNot($employe->id)->exists()) {
                throw new \DomainException('Cette adresse e-mail est déjà rattachée à un autre membre du personnel.');
            }

            if (! $user) {
                $motDePasse = Str::password(12);
                $userId = $this->utilisateurService->creerCompte([
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
                $user = User::query()->findOrFail($userId);
            } else {
                $this->utilisateurService->reactiverCompte($user->id, $email);
            }

            $this->utilisateurService->synchroniserRoles($user->id, $role->id);
            $libellePoste = $employe->posteAdministratif?->nom ?? $role->name;
            $employe->update(['user_id' => $user->id, 'email' => $email, 'role_id' => $role->id, 'poste' => $libellePoste]);

            return ['user' => $user, 'mot_de_passe_temporaire' => $motDePasse];
        });
    }

    public function embaucher(array $donnees): array
    {
        return DB::transaction(function () use ($donnees): array {
            $candidature = null;
            if (! empty($donnees['candidature_id'])) {
                $candidature = Candidature::query()->lockForUpdate()->findOrFail((int) $donnees['candidature_id']);
                if ($candidature->statut !== 'retenue' || $candidature->employe_id !== null) {
                    throw new \DomainException('Cette candidature n’est plus disponible pour une intégration.');
                }
            }
            $email = mb_strtolower(trim((string) $donnees['email']));

            $motDePasse = Str::password(12);
            $role = Role::query()->findOrFail((int) $donnees['role_id']);
            $posteAdministratif = ! empty($donnees['poste_administratif_id'])
                ? PosteAdministratif::query()->find((int) $donnees['poste_administratif_id'])
                : null;
            if (! $posteAdministratif && ! empty($candidature?->poste_souhaite)) {
                $posteAdministratif = PosteAdministratif::query()->where('nom', $candidature->poste_souhaite)->first();
                if ($posteAdministratif) {
                    $donnees['poste_administratif_id'] = $posteAdministratif->id;
                }
            }
            $ligneSalariale = ! empty($donnees['grille_salariale_id'])
                ? DB::table('grilles_salariales')->where('actif', true)->where('id', $donnees['grille_salariale_id'])->first()
                : null;
            if (! empty($donnees['grille_salariale_id']) && ! $ligneSalariale) {
                throw new \DomainException('La ligne de grille salariale sélectionnée est indisponible.');
            }
            $formule = $donnees['formule_paie'] ?? $this->determinerFormulePaie(
                (bool) ($donnees['responsabilite_fixe'] ?? false),
                (bool) ($donnees['responsabilite_horaire'] ?? false),
            );
            if (! in_array($formule, ['fixe', 'horaire', 'mixte'], true)) {
                throw new \DomainException('Le mode de rémunération sélectionné est invalide.');
            }

            $userId = $this->utilisateurService->creerCompte([
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

            $this->utilisateurService->synchroniserRoles($userId, $role->id);
            $dateEmbauche = now()->toDateString();

            $employe = Employe::query()->create(array_merge(
                Arr::only($donnees, [
                    'nom', 'prenom', 'date_naissance', 'sexe', 'telephone', 'email',
                    'photo', 'role_id', 'poste_administratif_id', 'niveau_id',
                ]),
                [
                    'user_id' => $userId,
                    'email' => $email,
                    'matricule' => $this->prochainMatricule(),
                    'poste' => $posteAdministratif?->nom ?? $donnees['poste'] ?? $role->name,
                    'date_embauche' => $dateEmbauche,
                    'statut' => 'actif',
                    'palier_anciennete_id' => PalierAnciennete::query()->where('anciennete_min_mois', 0)->where('actif', true)->value('id'),
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

            $candidature?->update([
                'statut' => 'embauche',
                'employe_id' => $employe->id,
            ]);

            return ['employe' => $employe, 'mot_de_passe_temporaire' => $motDePasse];
        });
    }

    public function creerEmploye(array $donnees): object
    {
        $donnees['date_embauche'] ??= now()->toDateString();

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

    public function muter(int $employeId, string|int $nouveauPoste, ?int $nouveauNiveauId, string $commentaire): void
    {
        DB::transaction(function () use ($employeId, $nouveauPoste, $nouveauNiveauId, $commentaire): void {
            $employe = Employe::findOrFail($employeId);
            $ancienPoste = $employe->poste;

            $posteAdmin = is_numeric($nouveauPoste)
                ? PosteAdministratif::find((int) $nouveauPoste)
                : PosteAdministratif::query()->where('nom', (string) $nouveauPoste)->first();
            $posteAdminId = $posteAdmin?->id ?? (is_numeric($nouveauPoste) ? (int) $nouveauPoste : null);
            $libellePoste = $posteAdmin?->nom ?? (string) $nouveauPoste;

            $estChangementPoste = ($posteAdminId && $posteAdminId !== $employe->poste_administratif_id) || ($libellePoste !== $employe->poste);

            if ($estChangementPoste) {
                $employe->motifChangementPoste = $commentaire;
            }

            $updateData = ['poste' => $libellePoste];
            if ($nouveauNiveauId !== null) {
                $updateData['niveau_id'] = $nouveauNiveauId;
            }
            if ($posteAdminId) {
                $updateData['poste_administratif_id'] = $posteAdminId;
            }

            $employe->update($updateData);

            $employe->historiqueCarriere()->create([
                'evenement' => 'mutation',
                'ancien_poste' => $ancienPoste,
                'nouveau_poste' => $libellePoste,
                'date_evenement' => now(),
                'commentaire' => $commentaire,
            ]);

            if ($employe->user_id && $nouveauNiveauId !== null) {
                app(UtilisateurServiceInterface::class)->mettreAJourIdentite((int) $employe->user_id, [
                    'niveau_id' => $nouveauNiveauId,
                ]);
            }
        });
    }

    public function promouvoir(int $employeId, string|int $nouveauPoste, string $commentaire, ?float $nouveauSalaire = null, ?float $nouveauTauxHoraire = null): void
    {
        DB::transaction(function () use ($employeId, $nouveauPoste, $commentaire, $nouveauSalaire, $nouveauTauxHoraire): void {
            $employe = Employe::findOrFail($employeId);
            $ancienPoste = $employe->poste;

            $posteAdmin = is_numeric($nouveauPoste)
                ? PosteAdministratif::find((int) $nouveauPoste)
                : PosteAdministratif::query()->where('nom', (string) $nouveauPoste)->first();
            $posteAdminId = $posteAdmin?->id ?? (is_numeric($nouveauPoste) ? (int) $nouveauPoste : null);
            $libellePoste = $posteAdmin?->nom ?? (string) $nouveauPoste;

            $estChangementPoste = ($posteAdminId && $posteAdminId !== $employe->poste_administratif_id) || ($libellePoste !== $employe->poste);

            if ($estChangementPoste) {
                $employe->motifChangementPoste = $commentaire;
            }

            $updateData = ['poste' => $libellePoste];
            if ($posteAdminId) {
                $updateData['poste_administratif_id'] = $posteAdminId;
            }

            $employe->update($updateData);

            $employe->historiqueCarriere()->create([
                'evenement' => 'promotion',
                'ancien_poste' => $ancienPoste,
                'nouveau_poste' => $libellePoste,
                'date_evenement' => now(),
                'commentaire' => $commentaire,
            ]);

            $contrat = $employe->contratPrincipal;
            if ($contrat) {
                $contratUpdates = [];
                if ($nouveauSalaire !== null && $nouveauSalaire >= 0) {
                    $contratUpdates['salaire_base'] = $nouveauSalaire;
                }
                if ($nouveauTauxHoraire !== null && $nouveauTauxHoraire >= 0) {
                    $contratUpdates['taux_horaire'] = $nouveauTauxHoraire;
                }
                if (! empty($contratUpdates)) {
                    $contrat->update($contratUpdates);
                }

                if ($posteAdminId && ($nouveauSalaire !== null || $nouveauTauxHoraire !== null)) {
                    ContratFonctionRemuneration::create([
                        'contrat_id' => $contrat->id,
                        'poste_administratif_id' => $posteAdminId,
                        'mode_remuneration' => $nouveauSalaire !== null ? 'salaire_base' : 'tarif_horaire',
                        'montant' => $nouveauSalaire ?? $nouveauTauxHoraire ?? 0,
                        'date_effet' => now(),
                    ]);
                }
            }
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

    public function ajouterDiplome(int $employeId, array $donnees): object
    {
        $employe = Employe::query()->findOrFail($employeId);

        $intitule = trim((string) ($donnees['nom'] ?? ''));
        if ($intitule === '') {
            throw new \InvalidArgumentException('L’intitulé du diplôme ou de la qualification est obligatoire.');
        }

        $type = $donnees['type'] ?? 'professionnel';
        if (! in_array($type, ['academique', 'professionnel'], true)) {
            $type = 'professionnel';
        }

        return DiplomePersonnel::create([
            'personnel_id' => $employe->id,
            'nom' => $intitule,
            'type' => $type,
            'etablissement' => ! empty($donnees['etablissement']) ? trim((string) $donnees['etablissement']) : null,
            'annee_obtention' => ! empty($donnees['annee_obtention']) ? (int) $donnees['annee_obtention'] : null,
            'document_id' => ! empty($donnees['document_id']) ? (int) $donnees['document_id'] : null,
        ]);
    }

    public function getDiplomes(int $employeId): Collection
    {
        return DiplomePersonnel::where('personnel_id', $employeId)
            ->orderByDesc('annee_obtention')
            ->orderBy('nom')
            ->get();
    }
}
