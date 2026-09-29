<?php

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Contracts\SeanceServiceInterface;
use App\Modules\Pedagogie\Exceptions\SeanceIntrouvableException;
use App\Modules\Pedagogie\Exceptions\TransitionStatutSeanceInvalideException;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Pedagogie\Models\Seance;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SeanceService implements SeanceServiceInterface
{
    public function __construct(
        private readonly ParametrageServiceContract $parametrage,
        private readonly AuditServiceContract $audit,
    ) {}

    public function genererSeancesPourSemaine(int $anneeScolaireId, \DateTimeInterface $lundiDeLaSemaine): void
    {
        $cours = EmploiDuTemps::where('annee_scolaire_id', $anneeScolaireId)
            ->where('actif', true)
            ->with('creneau')
            ->get();

        foreach ($cours as $coursTheorique) {
            $dateSeance = Carbon::parse($lundiDeLaSemaine)
                ->addDays($coursTheorique->creneau->jour_semaine - 1);

            if ($this->parametrage->estJourFerie($dateSeance)) {
                continue;
            }

            // firstOrCreate : le Job hebdomadaire doit pouvoir être relancé sans
            // risque après un échec partiel, sans créer de doublons de séances.
            Seance::firstOrCreate(
                ['emploi_du_temps_id' => $coursTheorique->id, 'date_seance' => $dateSeance],
                ['enseignant_id' => $coursTheorique->enseignant_id, 'statut' => 'programmee']
            );
        }
    }

    public function marquerCommencee(int $seanceId): void
    {
        $this->transiter($seanceId, 'commencee');
    }

    public function marquerCommenceeSiNecessaire(int $seanceId): void
    {
        $seance = Seance::find($seanceId);

        if (! $seance) {
            throw new SeanceIntrouvableException($seanceId);
        }

        if ($seance->statut === 'programmee') {
            $this->transiter($seanceId, 'commencee');
        }
    }

    public function marquerDispensee(int $seanceId): void
    {
        $this->transiter($seanceId, 'dispensee');
    }

    public function annuler(int $seanceId, string $motif): void
    {
        $seance = $this->transiter($seanceId, 'annulee');

        $this->audit->enregistrerAvecMotif($seance, "Annulation de la séance #{$seanceId}", $motif);
    }

    public function reporter(int $seanceId, \DateTimeInterface $nouvelleDate): void
    {
        $seance = $this->transiter($seanceId, 'reportee');

        Seance::create([
            'emploi_du_temps_id' => $seance->emploi_du_temps_id,
            'date_seance' => $nouvelleDate,
            'enseignant_id' => $seance->enseignant_id,
            'statut' => 'programmee',
        ]);
    }

    public function getSeancesDuJour(int $classeId, \DateTimeInterface $date): Collection
    {
        return Seance::whereHas('emploiDuTemps', fn ($q) => $q->where('classe_id', $classeId))
            ->where('date_seance', $date->format('Y-m-d'))
            ->with('emploiDuTemps')
            ->get();
    }

    public function getSeancesSansProgression(\DateTimeInterface $depuis): Collection
    {
        return Seance::where('statut', 'dispensee')
            ->where('progression_renseignee', false)
            ->where('date_seance', '>=', $depuis)
            ->get();
    }

    public function existe(int $seanceId): bool
    {
        return Seance::whereKey($seanceId)->exists();
    }

    public function getStatut(int $seanceId): string
    {
        $seance = Seance::find($seanceId);

        if (! $seance) {
            throw new SeanceIntrouvableException($seanceId);
        }

        return $seance->statut;
    }

    public function marquerProgressionRenseignee(int $seanceId): void
    {
        $seance = Seance::find($seanceId);

        if (! $seance) {
            throw new SeanceIntrouvableException($seanceId);
        }

        $seance->update(['progression_renseignee' => true]);
    }

    /**
     * Applique une transition de statut en vérifiant qu'elle est autorisée.
     * Centralise la règle pour éviter qu'une séance annulée passe "dispensée"
     * par erreur (bug ou appel concurrent).
     */
    private function transiter(int $seanceId, string $statutDemande): Seance
    {
        $seance = Seance::find($seanceId);

        if (! $seance) {
            throw new SeanceIntrouvableException($seanceId);
        }

        $autorisees = Seance::TRANSITIONS_AUTORISEES[$seance->statut] ?? [];

        if (! in_array($statutDemande, $autorisees, true)) {
            throw new TransitionStatutSeanceInvalideException($seance->statut, $statutDemande);
        }

        $seance->update(['statut' => $statutDemande]);

        return $seance;
    }
}
