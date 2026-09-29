<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Services;

use App\Modules\Scolarite\Contracts\TableauHonneurServiceContract;
use App\Modules\Scolarite\Exceptions\MotifAnnulationRequisException;
use App\Modules\Scolarite\Models\TableauHonneur;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class TableauHonneurService implements TableauHonneurServiceContract
{
    public function __construct(
        private readonly AuditServiceContract $auditService,
    ) {}

    public function composer(int $eleveId, int $periodeId, int $anneeScolaireId, ?string $mention = null): TableauHonneur
    {
        $tableau = TableauHonneur::create([
            'eleve_id' => $eleveId,
            'periode_id' => $periodeId,
            'annee_scolaire_id' => $anneeScolaireId,
            'mention' => $mention,
            'compose_par' => Auth::id(),
        ]);

        // Composition manuelle routinière (pas une annulation) : enregistrer(),
        // pas enregistrerAvecMotif() — voir AuditServiceContract.
        $this->auditService->enregistrer(
            $tableau,
            "Élève #{$eleveId} ajouté au tableau d'honneur de la période #{$periodeId}",
        );

        return $tableau;
    }

    public function getTableauDeLaPeriode(int $periodeId, int $anneeScolaireId): Collection
    {
        return TableauHonneur::where('periode_id', $periodeId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->with('eleve')
            ->get();
    }

    public function retirer(int $tableauHonneurId, string $motif): void
    {
        if (trim($motif) === '') {
            throw new MotifAnnulationRequisException;
        }

        $tableau = TableauHonneur::findOrFail($tableauHonneurId);

        // Pas de statut/soft-delete sur cette table (composition purement
        // manuelle, voir Module 5 §1) : le motif est conservé via le
        // Socle — jamais d'écriture directe dans la table audits — puis la
        // ligne est retirée. Véritable annulation avec motif obligatoire —
        // enregistrerAvecMotif() (le vrai motif, pas un code d'événement).
        $this->auditService->enregistrerAvecMotif(
            $tableau,
            "Élève #{$tableau->eleve_id} retiré du tableau d'honneur de la période #{$tableau->periode_id} : {$motif}",
            $motif,
        );

        $tableau->delete();
    }
}
