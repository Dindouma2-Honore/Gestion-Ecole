<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Contracts\PortailParentServiceContract;
use App\Modules\Communication\Exceptions\AccesNonAutoriseException;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class PortailParentService implements PortailParentServiceContract
{
    public function __construct(private readonly PaiementServiceContract $paiements) {}

    public function getVueEnfant(int $parentId, int $eleveId): object
    {
        if (! $this->parentAutoriseVoirEnfant($parentId, $eleveId)) {
            throw new AccesNonAutoriseException($parentId, $eleveId);
        }

        $eleve = DB::table('eleves')->where('id', $eleveId)->first();

        $identite = $eleve ? [
            'id' => $eleve->id,
            'matricule' => $eleve->matricule_permanent ?? '',
            'nom' => $eleve->nom ?? '',
            'prenom' => $eleve->prenom ?? '',
            'date_naissance' => $eleve->date_naissance ?? '',
            'sexe' => $eleve->sexe ?? '',
            'statut' => $eleve->statut ?? '',
        ] : null;

        $anneeId = null;
        if (App::bound(AnneeScolaireServiceContract::class)) {
            try {
                $anneeService = App::make(AnneeScolaireServiceContract::class);
                $anneeId = $anneeService->getAnneeCouranteId();
            } catch (\Throwable $e) {
                // Fallback
            }
        }

        $resteAPayer = 0.0;
        if ($anneeId !== null) {
            try {
                $resteAPayer = $this->paiements->getResteAPayer($eleveId, $anneeId);
            } catch (\Throwable) {
                $resteAPayer = 0.0;
            }
        }
        $historiquePaiements = $this->paiements->getHistoriquePaiements($eleveId);

        $resultats = [];
        $tauxPresence = 100.0;
        $absencesNonJustifiees = [];

        return (object) [
            'identite' => (object) ($identite ?? []),
            'reste_a_payer' => $resteAPayer,
            'historique_paiements' => $historiquePaiements,
            'resultats' => $resultats,
            'taux_presence' => $tauxPresence,
            'absences_non_justifiees' => $absencesNonJustifiees,
        ];
    }

    public function parentAutoriseVoirEnfant(int $parentId, int $eleveId): bool
    {
        return DB::table('eleve_parent')
            ->where('parent_id', $parentId)
            ->where('eleve_id', $eleveId)
            ->exists();
    }
}
