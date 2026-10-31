<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Contracts\MatiereServiceInterface;
use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Contracts\ProgressionServiceInterface;
use App\Modules\Pedagogie\Contracts\SeanceServiceInterface;
use App\Modules\Pedagogie\Exceptions\SeanceNonDispenseeException;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\ProgrammeChapitre;
use App\Modules\Pedagogie\Models\ProgrammeLecon;
use App\Modules\Pedagogie\Models\Progression;
use App\Modules\Pedagogie\Models\Seance;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProgressionService implements ProgressionServiceInterface
{
    public function __construct(
        private readonly MatiereServiceInterface $matiere,
        private readonly SeanceServiceInterface $seanceService,
        private readonly ?ProgrammeServiceInterface $programmeService = null,
        private readonly ?ClasseServiceInterface $classeService = null,
        private readonly ?ProgressionCalculator $calculator = null,
        private readonly ?EnseignantServiceInterface $enseignantService = null,
    ) {}

    public function saisirProgression(
        int $seanceId,
        ?int $chapitreId,
        string $contenu,
        ?string $devoirs = null,
        bool $chapitreTermine = false,
        ?int $leconId = null,
        string $statutElement = 'terminee',
        ?int $dureeMinutes = null,
        ?array $devoirData = null,
        ?string $observation = null,
        ?string $dateProgression = null,
    ): object {
        $statut = $this->seanceService->getStatut($seanceId);

        if (! in_array($statut, ['dispensee', 'commencee'], true)) {
            throw new SeanceNonDispenseeException($seanceId, $statut);
        }

        if ($chapitreId === null) {
            throw new \DomainException('Vous devez confirmer le chapitre traité pendant la séance.');
        }
        if (! in_array($statutElement, ['commencee', 'poursuivie', 'terminee'], true)) {
            throw new InvalidArgumentException('L’état de progression sélectionné est invalide.');
        }

        $seance = Seance::with('emploiDuTemps')->findOrFail($seanceId);
        $emploi = $seance->emploiDuTemps;
        $element = ProgrammeChapitre::with('programme')->find($chapitreId);
        if ($element?->programme?->analyse_statut !== null) {
            if ($devoirs !== null && trim($devoirs) !== '') {
                throw new \DomainException('Renseignez les informations du devoir dans le formulaire de progression de séance.');
            }

            return app(SessionProgressService::class)->enregistrer([
                'seance_id' => $seanceId, 'chapitre_id' => $chapitreId,
                'etat_avancement' => $chapitreTermine ? 'terminee' : 'en_cours',
                'contenu_couvert' => $contenu, 'devoir_donne' => false,
            ]);
        }

        $chapitresAutorises = collect($this->programmeService?->getChapitresPrevus(
            $emploi->matiere_id, $emploi->classe_id, $emploi->annee_scolaire_id, $emploi->enseignant_id
        ))->pluck('id');
        if (! $chapitresAutorises->contains($chapitreId)) {
            throw new \DomainException('Ce chapitre ne correspond pas au programme validé de cette matière et de cette classe.');
        }
        if (Progression::where('chapitre_id', $chapitreId)->where('chapitre_termine', true)
            ->whereHas('seance', fn ($q) => $q->where('statut', 'dispensee')->whereHas('emploiDuTemps', fn ($q) => $q
                ->where('classe_id', $emploi->classe_id)->where('annee_scolaire_id', $emploi->annee_scolaire_id)))
            ->exists()) {
            throw new \DomainException('Ce chapitre est déjà terminé. Le chapitre suivant doit être sélectionné.');
        }

        $offre = OffrePedagogique::query()
            ->where('classe_id', $emploi->classe_id)
            ->where('matiere_id', $emploi->matiere_id)
            ->where('annee_scolaire_id', $emploi->annee_scolaire_id)
            ->where('actif', true)
            ->first();
        if ($offre === null) {
            throw new \DomainException('Aucune offre pédagogique active ne correspond à cette séance.');
        }

        $acteurAdministratif = $this->estProfilAdministratif();
        $enseignantConnecteId = Auth::id() && $this->enseignantService !== null
            ? $this->enseignantService->getEnseignantIdPourUtilisateur((int) Auth::id())
            : null;
        if (! $acteurAdministratif) {
            $affecte = $enseignantConnecteId !== null
                && $enseignantConnecteId === (int) $seance->enseignant_id
                && AffectationPedagogique::query()
                    ->where('enseignant_id', $enseignantConnecteId)
                    ->where('offre_pedagogique_id', $offre->id)
                    ->where('classe_id', $emploi->classe_id)
                    ->where('annee_scolaire_id', $emploi->annee_scolaire_id)
                    ->where('actif', true)
                    ->exists();
            if (! $affecte) {
                throw new InvalidArgumentException("Vous n'êtes pas affecté à cette offre pédagogique.");
            }
        }

        $programme = Programme::query()
            ->whereIn('statut', ['valide', 'publie', 'soumis'])
            ->where(function ($query) use ($offre): void {
                $query->where('offre_pedagogique_id', $offre->id)
                    ->orWhere('id', $offre->programme_id);
            })
            ->orderByDesc('version')->orderByDesc('id')->first();
        $chapitre = ProgrammeChapitre::query()->whereKey($chapitreId)->where('programme_id', $programme?->id)->first();
        if ($programme === null || $chapitre === null) {
            throw new InvalidArgumentException('Le chapitre choisi ne fait pas partie du programme validé de cette offre.');
        }

        $chapitreDejaTermineDansCetteClasse = Progression::query()
            ->where('chapitre_id', $chapitreId)
            ->where(function ($query) use ($emploi): void {
                $query->whereHas('seance.emploiDuTemps', fn ($schedule) => $schedule
                    ->where('classe_id', $emploi->classe_id)
                    ->where('matiere_id', $emploi->matiere_id)
                    ->where('annee_scolaire_id', $emploi->annee_scolaire_id));
            })
            ->where(fn ($query) => $query->where('chapitre_termine', true)->orWhere('statut_element', 'terminee'))
            ->exists();
        if ($chapitreDejaTermineDansCetteClasse) {
            throw new \DomainException('Ce chapitre est déjà terminé pour cette classe. Sélectionnez le chapitre suivant.');
        }

        if ($leconId !== null) {
            $lecon = ProgrammeLecon::where('programme_chapitre_id', $chapitreId)->find($leconId);
            if (! $lecon) {
                throw new \DomainException('La leçon spécifiée n\'appartient pas à ce chapitre.');
            }
        }

        $offresPartageantProgramme = DB::table('offres_pedagogiques')->where('programme_id', $programme->id)->pluck('id')->all();
        if ($programme->offre_pedagogique_id !== null) {
            $offresPartageantProgramme[] = (int) $programme->offre_pedagogique_id;
        }
        $programmePartage = count(array_unique($offresPartageantProgramme)) > 1;

        $progression = DB::transaction(function () use ($seance, $statut, $seanceId, $chapitreId, $contenu, $devoirs, $chapitreTermine, $leconId, $statutElement, $dureeMinutes, $observation, $dateProgression) {
            if ($statut === 'commencee') {
                $this->seanceService->marquerDispensee($seanceId);
            }

            $progression = Progression::create([
                'seance_id' => $seanceId,
                'chapitre_id' => $chapitreId,
                'lecon_id' => $leconId,
                'contenu_couvert' => $contenu,
                'chapitre_termine' => $chapitreTermine,
                'statut_element' => $statutElement,
                'duree_minutes' => $dureeMinutes,
                'date_progression' => $dateProgression ?? $seance->date_seance ?? now()->toDateString(),
                'observation' => $observation,
                'devoirs_donnes' => $devoirs,
                'saisi_par' => Auth::id(),
            ]);
            // The reference structure is shared; advancement belongs to session declarations per class.
            $this->seanceService->marquerProgressionRenseignee($seanceId);

            return $progression;
        });

        return $progression;
    }

    private function estProfilAdministratif(): bool
    {
        $user = Auth::user();

        return $user !== null && ($user->hasAnyRole(['Fondateur', 'Admin', 'Administrateur', 'Super Admin', 'Directeur']) || $user->can('pedagogie.progression.superviser'));
    }

    public function declarerChapitreCouvert(int $enseignantId, int $chapitreId, ?string $commentaire = null): object
    {
        throw new \DomainException('Une progression doit obligatoirement provenir d’une séance dispensée.');
    }

    public function getPourcentageAvancement(int $matiereId, int $classeId, int $anneeScolaireId): float
    {
        if ($this->calculator !== null) {
            $calcul = $this->calculator->calculerProgressionClasse($matiereId, $classeId, $anneeScolaireId);
            if ($calcul['elements_total'] > 0) {
                return $calcul['taux_simple'];
            }
        }

        if ($this->programmeService === null) {
            return 0.0;
        }

        $chapitresPrevus = $this->programmeService->getChapitresPrevus($matiereId, $classeId, $anneeScolaireId);
        $totalCount = $chapitresPrevus->count();

        if ($totalCount === 0) {
            return 0.0;
        }

        return app(ProgressionCalculator::class)->pourChapitres($chapitresPrevus, $classeId, $anneeScolaireId)['pourcentage'];
    }

    public function getRetardPedagogique(int $matiereId, int $classeId): array
    {
        if ($this->programmeService === null) {
            return [];
        }

        $chapitresPrevus = $this->programmeService->getChapitresPrevus($matiereId, $classeId, $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId());
        $completedIds = Progression::whereIn('chapitre_id', $chapitresPrevus->pluck('id'))
            ->where('chapitre_termine', true)
            ->whereHas('seance', fn ($q) => $q->where('statut', 'dispensee')->whereHas('emploiDuTemps', fn ($q) => $q->where('classe_id', $classeId)->where('annee_scolaire_id', $anneeId)))
            ->pluck('chapitre_id')
            ->unique()
            ->all();

        $chapitresEnRetard = $chapitresPrevus->reject(fn ($chapitre) => in_array($chapitre->id, $completedIds, true))->values();

        return [
            'nombre_chapitres_retard' => $chapitresEnRetard->count(),
            'chapitres' => $chapitresEnRetard->toArray(),
        ];
    }

    public function comparerProgressionNiveau(int $matiereId, int $niveauId, int $anneeScolaireId): array
    {
        $classes = $this->classeService !== null
            ? $this->classeService->getClassesParNiveau($niveauId, $anneeScolaireId)
            : [];

        $classesProgression = [];
        $totalProgression = 0.0;

        foreach ($classes as $classeInfo) {
            $classeId = (int) $classeInfo['id'];
            $nomClasse = (string) $classeInfo['nom'];
            $pct = $this->getPourcentageAvancement($matiereId, $classeId, $anneeScolaireId);

            $chapitresPrevus = $this->programmeService?->getChapitresPrevus($matiereId, $classeId, $anneeScolaireId) ?? collect();
            $totalChapitres = $chapitresPrevus->count();
            $completedChapitres = $totalChapitres > 0
                ? Progression::whereIn('chapitre_id', $chapitresPrevus->pluck('id'))
                    ->whereNotNull('chapitre_id')
                    ->distinct()
                    ->count('chapitre_id')
                : 0;

            $classesProgression[] = [
                'classe_id' => $classeId,
                'nom' => $nomClasse,
                'progression' => $pct,
                'chapitres_termines' => $completedChapitres,
                'total_chapitres' => $totalChapitres,
            ];

            $totalProgression += $pct;
        }

        $moyenne = count($classesProgression) > 0
            ? round($totalProgression / count($classesProgression), 2)
            : 0.0;

        return [
            'niveau_id' => $niveauId,
            'matiere_id' => $matiereId,
            'classes' => $classesProgression,
            'moyenne_niveau' => $moyenne,
        ];
    }
}
