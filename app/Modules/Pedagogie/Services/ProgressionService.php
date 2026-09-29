<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Contracts\MatiereServiceInterface;
use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Contracts\ProgressionServiceInterface;
use App\Modules\Pedagogie\Contracts\SeanceServiceInterface;
use App\Modules\Pedagogie\Exceptions\SeanceNonDispenseeException;
use App\Modules\Pedagogie\Models\ProgrammeChapitre;
use App\Modules\Pedagogie\Models\Progression;
use Illuminate\Support\Facades\Auth;

class ProgressionService implements ProgressionServiceInterface
{
    public function __construct(
        private readonly MatiereServiceInterface $matiere,
        private readonly SeanceServiceInterface $seanceService,
        private readonly ?ProgrammeServiceInterface $programmeService = null,
    ) {}

    public function saisirProgression(int $seanceId, ?int $chapitreId, string $contenu, ?string $devoirs = null): object
    {
        $statut = $this->seanceService->getStatut($seanceId);

        if ($statut !== 'dispensee') {
            throw new SeanceNonDispenseeException($seanceId, $statut);
        }

        $progression = Progression::create([
            'seance_id' => $seanceId,
            'chapitre_id' => $chapitreId,
            'contenu_couvert' => $contenu,
            'devoirs_donnes' => $devoirs,
            'saisi_par' => Auth::id(),
        ]);

        $this->seanceService->marquerProgressionRenseignee($seanceId);

        return $progression;
    }

    public function declarerChapitreCouvert(int $enseignantId, int $chapitreId, ?string $commentaire = null): object
    {
        ProgrammeChapitre::findOrFail($chapitreId);

        $progression = Progression::whereNull('seance_id')
            ->where('chapitre_id', $chapitreId)
            ->where('saisi_par', $enseignantId)
            ->first();

        $contenu = $commentaire !== null && trim($commentaire) !== ''
            ? $commentaire
            : 'Chapitre déclaré comme déjà couvert (déclaration hors séance).';

        if ($progression) {
            $progression->update(['contenu_couvert' => $contenu]);

            return $progression;
        }

        return Progression::create([
            'seance_id' => null,
            'chapitre_id' => $chapitreId,
            'contenu_couvert' => $contenu,
            'saisi_par' => $enseignantId,
        ]);
    }

    public function getPourcentageAvancement(int $matiereId, int $classeId, int $anneeScolaireId): float
    {
        if ($this->programmeService === null) {
            return 0.0;
        }

        $chapitresPrevus = $this->programmeService->getChapitresPrevus($matiereId, $classeId, $anneeScolaireId);
        $totalCount = $chapitresPrevus->count();

        if ($totalCount === 0) {
            return 0.0;
        }

        $chapitreIds = $chapitresPrevus->pluck('id')->all();
        $completedCount = Progression::whereIn('chapitre_id', $chapitreIds)
            ->whereNotNull('chapitre_id')
            ->distinct()
            ->count('chapitre_id');

        return round(($completedCount / $totalCount) * 100, 2);
    }

    public function getRetardPedagogique(int $matiereId, int $classeId): array
    {
        if ($this->programmeService === null) {
            return [];
        }

        $chapitresPrevus = $this->programmeService->getChapitresPrevus($matiereId, $classeId, 1);
        $completedIds = Progression::whereIn('chapitre_id', $chapitresPrevus->pluck('id'))
            ->whereNotNull('chapitre_id')
            ->pluck('chapitre_id')
            ->unique()
            ->all();

        $chapitresEnRetard = $chapitresPrevus->reject(fn ($chapitre) => in_array($chapitre->id, $completedIds, true))->values();

        return [
            'nombre_chapitres_retard' => $chapitresEnRetard->count(),
            'chapitres' => $chapitresEnRetard->toArray(),
        ];
    }
}
