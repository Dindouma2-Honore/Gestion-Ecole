<?php

namespace App\Modules\RH\Services;

use App\Models\User;
use App\Modules\RH\Contracts\CongeServiceContract;
use App\Modules\RH\Exceptions\ChevauchementCongeException;
use App\Modules\RH\Exceptions\SoldeCongeInsuffisantException;
use App\Modules\RH\Models\Conge;
use App\Modules\RH\Models\SoldeConge;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Carbon\CarbonPeriod;

class CongeService implements CongeServiceContract
{
    public function __construct(
        private readonly ParametrageServiceContract $parametrage,
        private readonly AnneeScolaireServiceContract $anneeScolaire
    ) {}

    public function demanderConge(int $employeId, string $type, \DateTimeInterface $debut, \DateTimeInterface $fin, ?string $motif): object
    {
        $this->verifierChevauchement($employeId, $debut, $fin);

        $nombreJours = $this->calculerJoursOuvrables($debut, $fin);

        if ($type === 'conge_annuel') {
            $this->verifierSoldeSuffisant($employeId, $nombreJours);
        }

        return Conge::create([
            'employe_id' => $employeId,
            'type' => $type,
            'date_debut' => $debut,
            'date_fin' => $fin,
            'nombre_jours' => $nombreJours,
            'motif' => $motif,
            'statut' => 'demande',
        ]);
    }

    private function calculerJoursOuvrables(\DateTimeInterface $debut, \DateTimeInterface $fin): float
    {
        $jours = 0;
        foreach (CarbonPeriod::create($debut, $fin) as $date) {
            if ($date->isWeekend()) {
                continue;
            }
            if ($this->parametrage->estJourFerie($date)) {
                continue;
            }
            $jours++;
        }

        return (float) $jours;
    }

    private function verifierChevauchement(int $employeId, \DateTimeInterface $debut, \DateTimeInterface $fin): void
    {
        $existe = Conge::where('employe_id', $employeId)
            ->whereIn('statut', ['demande', 'approuve', 'en_cours'])
            ->where('date_debut', '<=', $fin)
            ->where('date_fin', '>=', $debut)
            ->exists();

        if ($existe) {
            throw new ChevauchementCongeException($employeId, $debut, $fin);
        }
    }

    private function verifierSoldeSuffisant(int $employeId, float $joursDemandes): void
    {
        $anneeId = $this->anneeScolaire->getAnneeCourante()?->id ?? 0;
        $solde = $this->getSoldeRestant($employeId, $anneeId);

        if ($joursDemandes > $solde) {
            throw new SoldeCongeInsuffisantException($employeId, $joursDemandes, $solde);
        }
    }

    public function getSoldeRestant(int $employeId, int $anneeScolaireId): float
    {
        $solde = SoldeConge::where('employe_id', $employeId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->first();

        return $solde ? (float) ($solde->jours_acquis - $solde->jours_pris) : 0.0;
    }

    public function estEnCongeAutorise(int $employeId, \DateTimeInterface $date): bool
    {
        return Conge::where('employe_id', $employeId)
            ->whereIn('statut', ['approuve', 'en_cours'])
            ->where('date_debut', '<=', $date)
            ->where('date_fin', '>=', $date)
            ->exists();
    }

    public function approuver(int $congeId, int $validateurId, ?string $commentaire = null): void
    {
        $conge = Conge::findOrFail($congeId);
        $validateur = User::find($validateurId);

        $conge->changerStatut('approuve', $validateur, $commentaire);

        if ($conge->type === 'conge_annuel') {
            $anneeId = $this->anneeScolaire->getAnneeCourante()?->id ?? 0;
            SoldeConge::where('employe_id', $conge->employe_id)
                ->where('annee_scolaire_id', $anneeId)
                ->increment('jours_pris', $conge->nombre_jours);
        }
    }

    public function rejeter(int $congeId, int $validateurId, string $motif): void
    {
        $conge = Conge::findOrFail($congeId);
        $validateur = User::find($validateurId);

        $conge->changerStatut('rejete', $validateur, $motif);
    }
}
