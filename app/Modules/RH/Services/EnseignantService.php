<?php

namespace App\Modules\RH\Services;

use App\Modules\RH\Contracts\EnseignantServiceInterface;
use App\Modules\RH\Models\Enseignant;
use App\Modules\RH\Models\EnseignantMatiereNiveau;
use App\Modules\RH\Models\Remplacement;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Illuminate\Support\Collection;

class EnseignantService implements EnseignantServiceInterface
{
    public function __construct(
        private readonly AnneeScolaireServiceContract $anneeScolaire
    ) {}

    public function getAffectationsCourantes(int $enseignantId): Collection
    {
        $anneeId = $this->anneeScolaire->getAnneeCourante()?->id;

        if (! $anneeId) {
            return collect();
        }

        return EnseignantMatiereNiveau::where('enseignant_id', $enseignantId)
            ->where('annee_scolaire_id', $anneeId)
            ->get();
    }

    public function estAffecteA(int $enseignantId, int $matiereId, int $classeId): bool
    {
        $anneeId = $this->anneeScolaire->getAnneeCourante()?->id;

        if (! $anneeId) {
            return false;
        }

        return EnseignantMatiereNiveau::where('enseignant_id', $enseignantId)
            ->where('matiere_id', $matiereId)
            ->where('niveau_id', $classeId)
            ->where('annee_scolaire_id', $anneeId)
            ->exists();
    }

    public function enregistrerRemplacement(int $enseignantAbsentId, ?int $remplacantId, \DateTimeInterface $debut, ?\DateTimeInterface $fin, string $motif): object
    {
        return Remplacement::create([
            'enseignant_absent_id' => $enseignantAbsentId,
            'enseignant_remplacant_id' => $remplacantId,
            'date_debut' => $debut,
            'date_fin' => $fin,
            'motif' => $motif,
        ]);
    }

    public function getEnseignantsDisponiblesPourRemplacement(int $niveauId, \DateTimeInterface $date): Collection
    {
        $anneeId = $this->anneeScolaire->getAnneeCourante()?->id;

        return Enseignant::whereHas('affectations', function ($q) use ($niveauId, $anneeId) {
            $q->where('niveau_id', $niveauId);
            if ($anneeId) {
                $q->where('annee_scolaire_id', $anneeId);
            }
        })
        ->whereDoesntHave('remplacementsCommeAbsent', function ($q) use ($date) {
            $q->where('date_debut', '<=', $date)
              ->where(function ($q2) use ($date) {
                  $q2->whereNull('date_fin')->orWhere('date_fin', '>=', $date);
              });
        })
        ->get();
    }

    public function existe(int $enseignantId): bool
    {
        return Enseignant::where('id', $enseignantId)->exists();
    }

    public function getChargeHoraire(int $enseignantId): float
    {
        $enseignant = Enseignant::find($enseignantId);

        return (float) ($enseignant?->charge_horaire_hebdo ?? 0);
    }
}
