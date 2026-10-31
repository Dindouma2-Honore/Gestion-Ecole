<?php

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Contracts\EmploiDuTempsServiceInterface;
use App\Modules\Pedagogie\Exceptions\ConflitEmploiDuTempsException;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\CreneauHoraire;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Logistique\Contracts\InfrastructureServiceInterface;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use DomainException;
use InvalidArgumentException;

class EmploiDuTempsService implements EmploiDuTempsServiceInterface
{
    public function __construct(
        private readonly ClasseServiceInterface $classeService,
    ) {}

    public function getEnseignantSuggere(int $classeId, ?int $matiereId, int $anneeScolaireId): ?int
    {
        if ($matiereId !== null) {
            $affectationMatiere = AffectationPedagogique::where('classe_id', $classeId)
                ->where('annee_scolaire_id', $anneeScolaireId)
                ->where('matiere_id', $matiereId)
                ->where('actif', true)
                ->first();

            if ($affectationMatiere !== null) {
                return (int) $affectationMatiere->enseignant_id;
            }
        }

        // Si pas trouvé ou pas de matière spécifique, chercher l'enseignant principal (primaire/maternelle)
        $enseignantPrincipalId = DB::table('classes')->where('id', $classeId)->value('professeur_principal_id');

        return $enseignantPrincipalId ? (int) $enseignantPrincipalId : null;
    }

    public function planifierCours(array $donnees): object
    {
        return DB::transaction(function () use ($donnees): EmploiDuTemps {
            // Déduction de l'enseignant si non spécifié explicitement.
            if (empty($donnees['enseignant_id']) && ! empty($donnees['classe_id']) && ! empty($donnees['annee_scolaire_id'])) {
                $suggere = $this->getEnseignantSuggere(
                    (int) $donnees['classe_id'],
                    isset($donnees['matiere_id']) ? (int) $donnees['matiere_id'] : null,
                    (int) $donnees['annee_scolaire_id']
                );

                if ($suggere !== null) {
                    $donnees['enseignant_id'] = $suggere;
                }
            }

            if (empty($donnees['enseignant_id'])) {
                throw new InvalidArgumentException('Un enseignant doit obligatoirement être spécifié ou affecté à cette classe.');
            }

            $this->assertPlanningModifiable((int) $donnees['classe_id'], (int) $donnees['annee_scolaire_id']);

            $conflits = $this->detecterConflits($donnees);
            if ($conflits !== []) {
                throw new ConflitEmploiDuTempsException($conflits);
            }

            $statutGroupe = $this->statutPlanning((int) $donnees['classe_id'], (int) $donnees['annee_scolaire_id']);
            $donnees['statut'] = in_array($statutGroupe, [EmploiDuTemps::STATUT_GENERE, EmploiDuTemps::STATUT_AJUSTE], true)
                ? EmploiDuTemps::STATUT_AJUSTE
                : EmploiDuTemps::STATUT_PREPARATION;
            $donnees['actif'] = false;

            $cours = EmploiDuTemps::create($donnees);
            if ($donnees['statut'] === EmploiDuTemps::STATUT_AJUSTE) {
                EmploiDuTemps::query()->where('classe_id', $donnees['classe_id'])
                    ->where('annee_scolaire_id', $donnees['annee_scolaire_id'])
                    ->whereIn('statut', [EmploiDuTemps::STATUT_PREPARATION, EmploiDuTemps::STATUT_GENERE, EmploiDuTemps::STATUT_AJUSTE, 'brouillon'])
                    ->update(['statut' => EmploiDuTemps::STATUT_AJUSTE, 'actif' => false]);
            }
            return $cours;
        });
    }

    public function modifierCours(int $id, array $donnees): object
    {
        return DB::transaction(function () use ($id, $donnees): EmploiDuTemps {
            $cours = EmploiDuTemps::query()->lockForUpdate()->findOrFail($id);
            $this->assertPlanningModifiable((int) $cours->classe_id, (int) $cours->annee_scolaire_id);
            if ((int) ($donnees['classe_id'] ?? $cours->classe_id) !== (int) $cours->classe_id
                || (int) ($donnees['annee_scolaire_id'] ?? $cours->annee_scolaire_id) !== (int) $cours->annee_scolaire_id) {
                throw new DomainException('Un créneau ne peut pas être déplacé vers une autre classe ou année scolaire pendant sa modification.');
            }
            $conflits = $this->detecterConflits($donnees, excludeId: $id);
            if ($conflits !== []) {
                throw new ConflitEmploiDuTempsException($conflits);
            }

            $statutGroupe = $this->statutPlanning((int) $cours->classe_id, (int) $cours->annee_scolaire_id);
            $statut = in_array($statutGroupe, [EmploiDuTemps::STATUT_GENERE, EmploiDuTemps::STATUT_AJUSTE], true)
                ? EmploiDuTemps::STATUT_AJUSTE
                : EmploiDuTemps::STATUT_PREPARATION;
            $donnees['statut'] = $statut;
            $donnees['actif'] = false;
            unset($donnees['publie_par'], $donnees['publie_le']);
            $cours->update($donnees);
            EmploiDuTemps::query()->where('classe_id', $cours->classe_id)
                ->where('annee_scolaire_id', $cours->annee_scolaire_id)
                ->whereIn('statut', [EmploiDuTemps::STATUT_PREPARATION, EmploiDuTemps::STATUT_GENERE, EmploiDuTemps::STATUT_AJUSTE, 'brouillon'])
                ->update(['statut' => $statut, 'actif' => false]);
            return $cours->refresh();
        });
    }

    public function detecterConflits(array $donnees, ?int $excludeId = null): array
    {
        $conflits = [];

        $creneau = CreneauHoraire::query()->lockForUpdate()->findOrFail($donnees['creneau_id']);
        $creneauxChevauchants = CreneauHoraire::query()
            ->where('jour_semaine', $creneau->jour_semaine)
            ->where('heure_debut', '<', $creneau->heure_fin)
            ->where('heure_fin', '>', $creneau->heure_debut)
            ->lockForUpdate()
            ->pluck('id');

        $base = fn (string $champ, mixed $valeur) => $this->coursPlanifiesQuery()
            ->where($champ, $valeur)
            ->whereIn('creneau_id', $creneauxChevauchants)
            ->where('annee_scolaire_id', $donnees['annee_scolaire_id'])
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId));

        if ($base('enseignant_id', $donnees['enseignant_id'])->exists()) {
            $conflits[] = 'enseignant_deja_occupe';
        }

        if ($base('salle_id', $donnees['salle_id'])->exists()) {
            $conflits[] = 'salle_deja_occupee';
        }

        if ($base('classe_id', $donnees['classe_id'])->exists()) {
            $conflits[] = 'classe_deja_occupee';
        }

        $enseignantActif = DB::table('enseignants as enseignants')
            ->join('employes', 'employes.id', '=', 'enseignants.employe_id')
            ->where('enseignants.id', $donnees['enseignant_id'])
            ->where('employes.statut', 'actif')
            ->exists();
        if (! $enseignantActif) {
            $conflits[] = 'enseignant_indisponible';
        }

        $matiereId = (int) ($donnees['matiere_id'] ?? 0);
        if ($matiereId > 0) {
            $offre = DB::table('offres_pedagogiques')
                ->where('classe_id', $donnees['classe_id'])
                ->where('matiere_id', $matiereId)
                ->where('annee_scolaire_id', $donnees['annee_scolaire_id'])
                ->where('actif', true)
                ->lockForUpdate()
                ->first(['heures_hebdomadaires']);

            if ($offre && $offre->heures_hebdomadaires !== null) {
                $heuresDejaPlacees = DB::table('emplois_du_temps as emplois')
                    ->join('creneaux_horaires as creneaux', 'creneaux.id', '=', 'emplois.creneau_id')
                    ->where('emplois.classe_id', $donnees['classe_id'])
                    ->where('emplois.matiere_id', $matiereId)
                    ->where('emplois.annee_scolaire_id', $donnees['annee_scolaire_id'])
                    ->where(fn ($query) => $query->where('emplois.actif', true)->orWhereIn('emplois.statut', $this->statutsModifiables()))
                    ->when($excludeId, fn ($query) => $query->where('emplois.id', '!=', $excludeId))
                    ->get(['creneaux.heure_debut', 'creneaux.heure_fin'])
                    ->sum(fn (object $creneau): float => $this->dureeEnHeures($creneau->heure_debut, $creneau->heure_fin));

                $heuresNouvelles = $this->dureeEnHeures($creneau->heure_debut, $creneau->heure_fin);
                if (($heuresDejaPlacees + $heuresNouvelles) > (float) $offre->heures_hebdomadaires) {
                    $conflits[] = 'volume_hebdomadaire_depasse';
                }
            }
        }

        return $conflits;
    }

    /** @return array<int, string> */
    public function verifierEmploiDuTemps(int $classeId, int $anneeScolaireId): array
    {
        $cours = $this->coursPlanifiesQuery()
            ->where('classe_id', $classeId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->with('creneau')
            ->get();
        $erreurs = [];
        $classe = DB::table('classes')->where('id', $classeId)->first(['professeur_principal_id']);

        if ($cours->isEmpty()) {
            return ['Aucun cours n’est enregistré pour cette classe et cette année scolaire.'];
        }

        foreach ($cours as $emploi) {
            $creneau = $emploi->creneau;
            $prefixe = 'Cours #'.$emploi->id.' : ';
            if (! $creneau || (int) $creneau->jour_semaine < 1 || (int) $creneau->jour_semaine > 6
                || $this->dureeEnHeures((string) $creneau->heure_debut, (string) $creneau->heure_fin) <= 0) {
                $erreurs[] = $prefixe.'créneau invalide (jour ou plage horaire).';
                continue;
            }

            $conflits = $this->detecterConflits([
                'classe_id' => $emploi->classe_id,
                'enseignant_id' => $emploi->enseignant_id,
                'salle_id' => $emploi->salle_id,
                'matiere_id' => $emploi->matiere_id,
                'creneau_id' => $emploi->creneau_id,
                'annee_scolaire_id' => $emploi->annee_scolaire_id,
            ], excludeId: $emploi->id);
            foreach ($conflits as $conflit) {
                $erreurs[] = $prefixe.match ($conflit) {
                    'classe_deja_occupee' => 'la classe a déjà un cours à cette heure.',
                    'enseignant_deja_occupe' => 'l’enseignant est déjà affecté ailleurs à cette heure.',
                    'salle_deja_occupee' => 'la salle est déjà réservée à cette heure.',
                    'enseignant_indisponible' => 'l’enseignant est inactif dans les Ressources humaines.',
                    'volume_hebdomadaire_depasse' => 'le volume hebdomadaire de l’offre est dépassé.',
                    default => $conflit,
                };
            }

            if ($emploi->matiere_id) {
                $offre = DB::table('offres_pedagogiques')->where('classe_id', $classeId)
                    ->where('matiere_id', $emploi->matiere_id)->where('annee_scolaire_id', $anneeScolaireId)
                    ->where('actif', true)->first(['id']);
                if (! $offre) {
                    $erreurs[] = $prefixe.'aucune offre pédagogique active ne correspond à cette matière et cette classe.';
                } else {
                    $affecte = DB::table('affectations_pedagogiques')
                        ->where('classe_id', $classeId)->where('annee_scolaire_id', $anneeScolaireId)
                        ->where('enseignant_id', $emploi->enseignant_id)->where('actif', true)
                        ->where(function ($query) use ($offre, $emploi): void {
                            $query->where('offre_pedagogique_id', $offre->id)
                                ->orWhere(function ($fallback) use ($emploi): void {
                                    $fallback->whereNull('offre_pedagogique_id')->where('matiere_id', $emploi->matiere_id);
                                });
                        })->exists();
                    if (! $affecte && (int) ($classe->professeur_principal_id ?? 0) !== (int) $emploi->enseignant_id) {
                        $erreurs[] = $prefixe.'l’enseignant n’est pas affecté à cette offre pédagogique.';
                    }
                }
            } elseif ((int) ($classe->professeur_principal_id ?? 0) !== (int) $emploi->enseignant_id
                && ! DB::table('affectations_pedagogiques')->where('classe_id', $classeId)
                    ->where('annee_scolaire_id', $anneeScolaireId)->where('enseignant_id', $emploi->enseignant_id)
                    ->where('actif', true)->exists()) {
                $erreurs[] = $prefixe.'l’enseignant n’a pas d’affectation active dans cette classe.';
            }
        }

        return array_values(array_unique($erreurs));
    }

    public function validerEmploiDuTemps(int $classeId, int $anneeScolaireId): void
    {
        DB::transaction(function () use ($classeId, $anneeScolaireId): void {
            $cours = $this->coursPlanifiesQuery()->where('classe_id', $classeId)
                ->where('annee_scolaire_id', $anneeScolaireId)->lockForUpdate()->get();
            if ($cours->isEmpty()) {
                throw new DomainException('Aucun cours n’est enregistré pour cette classe et cette année scolaire.');
            }
            $this->assertPlanningModifiable($classeId, $anneeScolaireId);
            $erreurs = $this->verifierEmploiDuTemps($classeId, $anneeScolaireId);
            if ($erreurs !== []) {
                throw new DomainException('Validation impossible : '.implode(' ', $erreurs));
            }
            EmploiDuTemps::query()->whereIn('id', $cours->pluck('id'))
                ->update(['statut' => EmploiDuTemps::STATUT_VALIDE, 'actif' => false]);
        });
    }

    public function publierEmploiDuTemps(int $classeId, int $anneeScolaireId): void
    {
        DB::transaction(function () use ($classeId, $anneeScolaireId): void {
            $cours = EmploiDuTemps::query()->where('classe_id', $classeId)
                ->where('annee_scolaire_id', $anneeScolaireId)
                ->where(fn ($query) => $query->where('actif', true)->orWhereIn('statut', $this->statutsModifiables()))
                ->lockForUpdate()->get();
            if ($cours->isEmpty()) {
                throw new DomainException('Aucun emploi du temps à publier pour cette classe et cette année scolaire.');
            }
            if ($cours->contains(fn (EmploiDuTemps $emploi): bool => $emploi->statut !== EmploiDuTemps::STATUT_VALIDE)) {
                throw new DomainException('L’emploi du temps doit être validé avant sa publication.');
            }
            EmploiDuTemps::query()->whereIn('id', $cours->pluck('id'))
                ->update([
                    'statut' => EmploiDuTemps::STATUT_PUBLIE,
                    'actif' => true,
                    'publie_par' => auth()->id(),
                    'publie_le' => now(),
                ]);
        });
    }

    private function assertPlanningModifiable(int $classeId, int $anneeScolaireId): void
    {
        $verrouille = EmploiDuTemps::query()->where('classe_id', $classeId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->whereIn('statut', [EmploiDuTemps::STATUT_VALIDE, EmploiDuTemps::STATUT_PUBLIE])
            ->lockForUpdate()
            ->exists();
        if ($verrouille) {
            throw new DomainException('Cet emploi du temps est validé ou publié ; ses créneaux sont protégés contre les modifications ordinaires.');
        }
    }

    private function statutPlanning(int $classeId, int $anneeScolaireId): string
    {
        $statuts = EmploiDuTemps::query()->where('classe_id', $classeId)
            ->where('annee_scolaire_id', $anneeScolaireId)->pluck('statut');
        foreach ([EmploiDuTemps::STATUT_PUBLIE, EmploiDuTemps::STATUT_VALIDE, EmploiDuTemps::STATUT_AJUSTE, EmploiDuTemps::STATUT_GENERE, EmploiDuTemps::STATUT_PREPARATION] as $statut) {
            if ($statuts->contains($statut) || ($statut === EmploiDuTemps::STATUT_PREPARATION && $statuts->contains('brouillon'))) {
                return $statut;
            }
        }
        return EmploiDuTemps::STATUT_PREPARATION;
    }

    public function getStatutEmploiDuTemps(int $classeId, int $anneeScolaireId): string
    {
        return $this->statutPlanning($classeId, $anneeScolaireId);
    }

    /** @return array<int, string> */
    private function statutsModifiables(): array
    {
        return [EmploiDuTemps::STATUT_PREPARATION, EmploiDuTemps::STATUT_GENERE, EmploiDuTemps::STATUT_AJUSTE, EmploiDuTemps::STATUT_VALIDE, 'brouillon'];
    }

    private function coursPlanifiesQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return EmploiDuTemps::query()->where(function ($query): void {
            $query->where('actif', true)->orWhereIn('statut', $this->statutsModifiables());
        });
    }

    /**
     * Remplit les créneaux libres en conservant les cours déjà enregistrés.
     * Les créneaux disponibles sont les plages déclarées dans creneaux_horaires.
     *
     * @return array{ajoutes:int, restants:array<string,float>}
     */
    public function genererProposition(int $classeId, int $anneeScolaireId): array
    {
        return DB::transaction(function () use ($classeId, $anneeScolaireId): array {
            $this->assertPlanningModifiable($classeId, $anneeScolaireId);
            $classe = DB::table('classes')
                ->where('id', $classeId)
                ->where('annee_scolaire_id', $anneeScolaireId)
                ->first(['id', 'professeur_principal_id']);
            if (! $classe) {
                throw new InvalidArgumentException('La classe sélectionnée ne fait pas partie de cette année scolaire.');
            }
            $statutAvantGeneration = $this->statutPlanning($classeId, $anneeScolaireId);

            $offres = DB::table('offres_pedagogiques as offres')
                ->join('matieres', 'matieres.id', '=', 'offres.matiere_id')
                ->where('offres.classe_id', $classeId)
                ->where('offres.annee_scolaire_id', $anneeScolaireId)
                ->where('offres.actif', true)
                ->whereNotNull('offres.heures_hebdomadaires')
                ->where('offres.heures_hebdomadaires', '>', 0)
                ->orderByDesc('offres.heures_hebdomadaires')
                ->get(['offres.id', 'offres.matiere_id', 'offres.heures_hebdomadaires', 'matieres.nom as matiere']);

            $affectations = DB::table('affectations_pedagogiques as affectations')
                ->join('enseignants', 'enseignants.id', '=', 'affectations.enseignant_id')
                ->join('employes', 'employes.id', '=', 'enseignants.employe_id')
                ->where('affectations.classe_id', $classeId)
                ->where('affectations.annee_scolaire_id', $anneeScolaireId)
                ->where('affectations.actif', true)
                ->where('employes.statut', 'actif')
                ->get(['affectations.offre_pedagogique_id', 'affectations.matiere_id', 'enseignants.id as enseignant_id', 'enseignants.charge_horaire_hebdo']);

            $enseignants = $affectations->pluck('enseignant_id')->unique()->values();
            if ($classe->professeur_principal_id) {
                $enseignants->push((int) $classe->professeur_principal_id);
            }
            $chargeMax = $enseignants->isEmpty()
                ? collect()
                : DB::table('enseignants')->whereIn('id', $enseignants)->pluck('charge_horaire_hebdo', 'id');

            $salles = app(InfrastructureServiceInterface::class)->getSallesDisponibles()->pluck('id')->map(fn ($id): int => (int) $id)->values();
            $creneaux = CreneauHoraire::query()
                ->whereBetween('jour_semaine', [1, 5])
                ->where('heure_debut', '>=', '07:30:00')
                ->where('heure_fin', '<=', '15:30:00')
                ->whereColumn('heure_fin', '>', 'heure_debut')
                ->orderBy('jour_semaine')
                ->orderBy('heure_debut')
                ->get();

            $events = DB::table('emplois_du_temps as emplois')
                ->join('creneaux_horaires as creneaux', 'creneaux.id', '=', 'emplois.creneau_id')
                ->where('emplois.annee_scolaire_id', $anneeScolaireId)
                ->where(fn ($query) => $query->where('emplois.actif', true)->orWhereIn('emplois.statut', $this->statutsModifiables()))
                ->get([
                    'emplois.id', 'emplois.classe_id', 'emplois.matiere_id', 'emplois.enseignant_id', 'emplois.salle_id',
                    'creneaux.jour_semaine', 'creneaux.heure_debut', 'creneaux.heure_fin',
                ])
                ->map(fn (object $event): array => (array) $event)
                ->all();

            $chargeEnseignants = [];
            foreach ($events as $event) {
                $teacher = (int) $event['enseignant_id'];
                $chargeEnseignants[$teacher] = ($chargeEnseignants[$teacher] ?? 0.0)
                    + $this->dureeEnHeures($event['heure_debut'], $event['heure_fin']);
            }

            $heuresPlacees = [];
            foreach ($events as $event) {
                if ((int) $event['classe_id'] === $classeId && $event['matiere_id'] !== null) {
                    $matiere = (int) $event['matiere_id'];
                    $heuresPlacees[$matiere] = ($heuresPlacees[$matiere] ?? 0.0)
                        + $this->dureeEnHeures($event['heure_debut'], $event['heure_fin']);
                }
            }

            $ajoutes = 0;
            $restants = [];
            foreach ($offres as $offre) {
                $matiereId = (int) $offre->matiere_id;
                $prevu = (float) $offre->heures_hebdomadaires;
                $reste = max(0.0, $prevu - ($heuresPlacees[$matiereId] ?? 0.0));

                $affectes = $affectations->filter(function (object $affectation) use ($offre, $matiereId): bool {
                    if ($affectation->offre_pedagogique_id !== null) {
                        return (int) $affectation->offre_pedagogique_id === (int) $offre->id;
                    }
                    return (int) $affectation->matiere_id === $matiereId;
                })->pluck('enseignant_id')->unique()->map(fn ($id): int => (int) $id)->values()->all();

                while ($reste > 0.0001) {
                    $candidats = [];
                    foreach ($creneaux as $creneau) {
                        $duree = $this->dureeEnHeures($creneau->heure_debut, $creneau->heure_fin);
                        if ($duree <= 0 || $duree > $reste + 0.0001) {
                            continue;
                        }

                        $eventCible = [
                            'classe_id' => $classeId,
                            'jour_semaine' => (int) $creneau->jour_semaine,
                            'heure_debut' => $creneau->heure_debut,
                            'heure_fin' => $creneau->heure_fin,
                        ];
                        if ($this->aChevauchement($events, $eventCible, 'classe_id', $classeId)) {
                            continue;
                        }

                        foreach ($affectes as $enseignantId) {
                            $chargeMaxHeures = $chargeMax[$enseignantId] ?? null;
                            if ($chargeMaxHeures !== null && ($chargeEnseignants[$enseignantId] ?? 0.0) + $duree > (float) $chargeMaxHeures) {
                                continue;
                            }
                            if ($this->aChevauchement($events, $eventCible, 'enseignant_id', $enseignantId)) {
                                continue;
                            }

                            foreach ($salles as $salleId) {
                                if ($this->aChevauchement($events, $eventCible, 'salle_id', $salleId)) {
                                    continue;
                                }
                                $chargeJour = collect($events)->filter(fn (array $event): bool =>
                                    (int) $event['classe_id'] === $classeId && (int) $event['jour_semaine'] === (int) $creneau->jour_semaine
                                )->sum(fn (array $event): float => $this->dureeEnHeures($event['heure_debut'], $event['heure_fin']));
                                $matiereJour = collect($events)->filter(fn (array $event): bool =>
                                    (int) $event['classe_id'] === $classeId && (int) $event['jour_semaine'] === (int) $creneau->jour_semaine && (int) ($event['matiere_id'] ?? 0) === $matiereId
                                )->count();
                                $candidats[] = [
                                    'creneau' => $creneau,
                                    'enseignant_id' => $enseignantId,
                                    'salle_id' => $salleId,
                                    'duree' => $duree,
                                    'score' => [$matiereJour, $chargeJour, (int) $creneau->jour_semaine, $creneau->heure_debut],
                                ];
                            }
                        }
                    }

                    if ($candidats === []) {
                        break;
                    }
                    usort($candidats, fn (array $a, array $b): int => $a['score'] <=> $b['score']);
                    $choix = $candidats[0];
                    $creneau = $choix['creneau'];
                    $cours = EmploiDuTemps::create([
                        'classe_id' => $classeId,
                        'matiere_id' => $matiereId,
                        'enseignant_id' => $choix['enseignant_id'],
                        'salle_id' => $choix['salle_id'],
                        'creneau_id' => $creneau->id,
                        'annee_scolaire_id' => $anneeScolaireId,
                        'actif' => false,
                        'statut' => $statutAvantGeneration === EmploiDuTemps::STATUT_AJUSTE
                            ? EmploiDuTemps::STATUT_AJUSTE
                            : EmploiDuTemps::STATUT_GENERE,
                    ]);
                    $event = [
                        'id' => $cours->id, 'classe_id' => $classeId, 'matiere_id' => $matiereId,
                        'enseignant_id' => $choix['enseignant_id'], 'salle_id' => $choix['salle_id'],
                        'jour_semaine' => (int) $creneau->jour_semaine,
                        'heure_debut' => $creneau->heure_debut, 'heure_fin' => $creneau->heure_fin,
                    ];
                    $events[] = $event;
                    $heuresPlacees[$matiereId] = ($heuresPlacees[$matiereId] ?? 0.0) + $choix['duree'];
                    $chargeEnseignants[$choix['enseignant_id']] = ($chargeEnseignants[$choix['enseignant_id']] ?? 0.0) + $choix['duree'];
                    $reste -= $choix['duree'];
                    $ajoutes++;
                }

                if ($reste > 0.0001) {
                    $restants[$offre->matiere] = round($reste, 2);
                }
            }

            if ($ajoutes > 0 && $statutAvantGeneration !== EmploiDuTemps::STATUT_AJUSTE) {
                EmploiDuTemps::query()->where('classe_id', $classeId)->where('annee_scolaire_id', $anneeScolaireId)
                    ->whereIn('statut', [EmploiDuTemps::STATUT_PREPARATION, EmploiDuTemps::STATUT_GENERE, 'brouillon'])
                    ->update(['statut' => EmploiDuTemps::STATUT_GENERE, 'actif' => false]);
            } elseif ($ajoutes > 0) {
                EmploiDuTemps::query()->where('classe_id', $classeId)->where('annee_scolaire_id', $anneeScolaireId)
                    ->whereIn('statut', [EmploiDuTemps::STATUT_PREPARATION, EmploiDuTemps::STATUT_GENERE, EmploiDuTemps::STATUT_AJUSTE, 'brouillon'])
                    ->update(['statut' => EmploiDuTemps::STATUT_AJUSTE, 'actif' => false]);
            }

            return ['ajoutes' => $ajoutes, 'restants' => $restants];
        });
    }

    private function aChevauchement(array $events, array $cible, string $champ, int $identifiant): bool
    {
        $debut = strtotime((string) $cible['heure_debut']);
        $fin = strtotime((string) $cible['heure_fin']);

        foreach ($events as $event) {
            if ((int) $event[$champ] !== $identifiant || (int) $event['jour_semaine'] !== (int) $cible['jour_semaine']) {
                continue;
            }
            $autreDebut = strtotime((string) $event['heure_debut']);
            $autreFin = strtotime((string) $event['heure_fin']);
            if ($debut !== false && $fin !== false && $autreDebut !== false && $autreFin !== false && $autreDebut < $fin && $autreFin > $debut) {
                return true;
            }
        }

        return false;
    }

    private function dureeEnHeures(string $debut, string $fin): float
    {
        $debutTimestamp = strtotime($debut);
        $finTimestamp = strtotime($fin);

        return $debutTimestamp !== false && $finTimestamp !== false && $finTimestamp > $debutTimestamp
            ? ($finTimestamp - $debutTimestamp) / 3600
            : 0.0;
    }

    public function getCoursDeLaSemaine(int $classeId, int $anneeScolaireId): Collection
    {
        return EmploiDuTemps::where('classe_id', $classeId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('actif', true)
            ->with(['creneau', 'matiere'])
            ->get()
            ->map(fn (EmploiDuTemps $cours) => (object) [
                'id' => $cours->id,
                'creneau' => $cours->creneau,
                'matiere' => $cours->matiere,
                'classe_nom' => $this->classeService->getNomClasse($cours->classe_id),
                'enseignant_id' => $cours->enseignant_id, // nom : via RH\EnseignantServiceInterface côté appelant
            ]);
    }

    public function getEmploiDuTempsEnseignant(int $enseignantId): Collection
    {
        return EmploiDuTemps::where('enseignant_id', $enseignantId)
            ->where('actif', true)
            ->with(['creneau', 'matiere'])
            ->get()
            ->map(fn (EmploiDuTemps $cours) => (object) [
                'id' => $cours->id,
                'creneau' => $cours->creneau,
                'matiere' => $cours->matiere,
                'classe_nom' => $this->classeService->getNomClasse($cours->classe_id),
            ]);
    }
}
