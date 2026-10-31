<?php

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Contracts\PresenceServiceInterface;
use App\Modules\Pedagogie\Contracts\SeanceServiceInterface;
use App\Modules\Pedagogie\Exceptions\AppelDejaEffectueException;
use App\Modules\Pedagogie\Models\AnomalieAppel;
use App\Modules\Pedagogie\Models\Presence;
use App\Modules\Pedagogie\Models\Seance;
use App\Modules\RH\Contracts\EnseignantServiceInterface;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PresenceService implements PresenceServiceInterface
{
    public function __construct(
        private readonly SeanceServiceInterface $seanceService,
        private readonly DocumentServiceContract $documentService,
        private readonly ?EleveServiceInterface $eleveService = null,
        private readonly ?EnseignantServiceInterface $enseignantService = null,
    ) {}

    public function faireAppel(int $seanceId, array $donneesEleves, bool $remplacer = false): void
    {
        if ($this->estAppelVerrouille($seanceId)) {
            throw new \DomainException('L\'appel de cette séance est verrouillé et ne peut plus être modifié.');
        }

        if (! $remplacer && $this->appelDejaFait($seanceId)) {
            throw new AppelDejaEffectueException($seanceId);
        }

        $seance = Seance::with('emploiDuTemps')->findOrFail($seanceId);
        $this->verifierAutorisationAppel($seance);

        DB::transaction(function () use ($seance, $seanceId, $donneesEleves) {
            // Principe métier : Tous les élèves inscrits dans la classe sont présents par défaut
            $tousElevesIds = [];
            if ($this->eleveService !== null && $seance->emploiDuTemps?->classe_id !== null) {
                $elevesInscrits = $this->eleveService->getElevesParClasse((int) $seance->emploiDuTemps->classe_id);
                $tousElevesIds = array_column($elevesInscrits, 'id');
            }

            // Normalisation des anomalies / saisies fournies
            $saisiesNormalisees = [];
            foreach ($donneesEleves as $cle => $valeur) {
                $eleveId = is_array($valeur) ? ($valeur['eleve_id'] ?? $cle) : $cle;
                $saisiesNormalisees[(int) $eleveId] = is_array($valeur) ? $valeur : ['statut' => $valeur];
            }

            // Chaque élève officiel a un statut (présent par défaut si non spécifié)
            $elevesATraiter = ! empty($tousElevesIds)
                ? array_unique(array_merge($tousElevesIds, array_keys($saisiesNormalisees)))
                : array_keys($saisiesNormalisees);

            foreach ($elevesATraiter as $eleveId) {
                $details = $saisiesNormalisees[$eleveId] ?? ['statut' => 'present'];
                Presence::updateOrCreate(
                    [
                        'seance_id' => $seanceId,
                        'eleve_id' => $eleveId,
                    ],
                    [
                        'statut' => $details['statut'] ?? 'present',
                        'motif' => $details['motif'] ?? null,
                        'heure_arrivee' => $details['heure_arrivee'] ?? null,
                        'saisi_par' => Auth::id(),
                    ]
                );
            }

            // La transition exige 'commencee' avant 'dispensee'. On force le
            // passage par 'commencee' si l'enseignant n'a pas cliqué ce bouton
            // avant l'appel, pour ne pas casser le flux "un seul clic" attendu.
            if ($seance->fresh()->statut !== 'dispensee') {
                $this->seanceService->marquerCommenceeSiNecessaire($seanceId);
                $this->seanceService->marquerDispensee($seanceId);
            }

            AnomalieAppel::where('seance_id', $seanceId)->update(['resolue' => true]);
        });
    }

    private function verifierAutorisationAppel(Seance $seance): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $estDirection = method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Fondateur', 'Directeur', 'Admin', 'Super Admin', 'Administrateur']);
        if ($estDirection) {
            return;
        }

        $enseignantId = $this->enseignantService !== null
            ? $this->enseignantService->getEnseignantIdPourUtilisateur((int) $user->id)
            : null;

        $estAffecte = ($enseignantId !== null && (int) $seance->enseignant_id === (int) $enseignantId)
            || (int) $seance->enseignant_id === (int) $user->id;

        if (! $estAffecte) {
            throw new \DomainException("Vous n'êtes pas autorisé à faire l'appel pour cette séance.");
        }
    }

    public function verrouillerAppel(int $seanceId): void
    {
        $this->seanceService->verrouillerAppel($seanceId);
    }

    public function estAppelVerrouille(int $seanceId): bool
    {
        return $this->seanceService->estAppelVerrouille($seanceId);
    }

    public function appelDejaFait(int $seanceId): bool
    {
        return Presence::where('seance_id', $seanceId)->exists();
    }

    public function justifierAbsence(int $presenceId, UploadedFile $justificatif): void
    {
        $presence = Presence::findOrFail($presenceId);

        $document = $this->documentService->attacher(
            $presence, $justificatif, 'justificatif_absence', 'interne'
        );

        $presence->update(['justifie' => true, 'document_justificatif_id' => $document->id]);
    }

    public function getTauxPresence(int $eleveId, int $periodeId): float
    {
        // TODO: filtrer par période réelle une fois les séances croisées avec
        // la date de séance — la doc source laissait ce filtre "à affiner"
        $total = Presence::where('eleve_id', $eleveId)->count();
        $presents = Presence::where('eleve_id', $eleveId)->where('statut', 'present')->count();

        return $total > 0 ? round(($presents / $total) * 100, 1) : 100.0;
    }

    public function getAbsencesNonJustifiees(int $eleveId, \DateTimeInterface $depuis): Collection
    {
        return Presence::where('eleve_id', $eleveId)
            ->where('statut', 'absent')
            ->where('justifie', false)
            ->whereHas('seance', fn ($q) => $q->where('date_seance', '>=', $depuis))
            ->get();
    }

    public function getStatutJour(int $eleveId, \DateTimeInterface $date): ?string
    {
        $presence = Presence::where('eleve_id', $eleveId)
            ->whereHas('seance', fn ($q) => $q->where('date_seance', $date->format('Y-m-d')))
            ->latest('id')
            ->first();

        return $presence?->statut;
    }

    public function enregistrerPointage(int $eleveId, int $seanceId, string $statut): void
    {
        if ($this->estAppelVerrouille($seanceId)) {
            throw new \DomainException('L\'appel de cette séance est verrouillé et ne peut plus être modifié.');
        }

        Presence::updateOrCreate(
            ['seance_id' => $seanceId, 'eleve_id' => $eleveId],
            ['statut' => $statut, 'saisi_par' => Auth::id()]
        );
    }
}
