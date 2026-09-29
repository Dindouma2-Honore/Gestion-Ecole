<?php

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Contracts\EmploiDuTempsServiceInterface;
use App\Modules\Pedagogie\Exceptions\ConflitEmploiDuTempsException;
use App\Modules\Pedagogie\Models\CreneauHoraire;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use Illuminate\Support\Collection;

class EmploiDuTempsService implements EmploiDuTempsServiceInterface
{
    public function __construct(
        private readonly ClasseServiceInterface $classeService,
        // RH\Contracts\EnseignantServiceInterface volontairement PAS injecté :
        // deptrac.yaml (que l'équipe ne souhaite pas modifier) n'autorise pas
        // PedagogieInternal -> RHContracts. Le nom de l'enseignant devra être
        // résolu côté appelant (Filament / autre module) à partir de
        // enseignant_id, pas ici. Voir README.
    ) {}

    public function planifierCours(array $donnees): object
    {
        $conflits = $this->detecterConflits($donnees);

        if (! empty($conflits)) {
            throw new ConflitEmploiDuTempsException($conflits);
        }

        return EmploiDuTemps::create($donnees);
    }

    public function modifierCours(int $id, array $donnees): object
    {
        // excludeId = $id : sinon le cours existant se détecte en conflit avec lui-même
        $conflits = $this->detecterConflits($donnees, excludeId: $id);

        if (! empty($conflits)) {
            throw new ConflitEmploiDuTempsException($conflits);
        }

        $cours = EmploiDuTemps::findOrFail($id);
        $cours->update($donnees);

        return $cours;
    }

    public function detecterConflits(array $donnees, ?int $excludeId = null): array
    {
        $conflits = [];

        $creneau = CreneauHoraire::findOrFail($donnees['creneau_id']);
        $creneauxChevauchants = CreneauHoraire::query()
            ->where('jour_semaine', $creneau->jour_semaine)
            ->where('heure_debut', '<', $creneau->heure_fin)
            ->where('heure_fin', '>', $creneau->heure_debut)
            ->pluck('id');

        $base = fn (string $champ, mixed $valeur) => EmploiDuTemps::where($champ, $valeur)
            ->whereIn('creneau_id', $creneauxChevauchants)
            ->where('annee_scolaire_id', $donnees['annee_scolaire_id'])
            ->where('actif', true)
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

        return $conflits;
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
