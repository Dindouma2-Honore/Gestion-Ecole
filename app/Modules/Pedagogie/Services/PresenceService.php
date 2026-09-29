<?php

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Contracts\PresenceServiceInterface;
use App\Modules\Pedagogie\Contracts\SeanceServiceInterface;
use App\Modules\Pedagogie\Exceptions\AppelDejaEffectueException;
use App\Modules\Pedagogie\Models\AnomalieAppel;
use App\Modules\Pedagogie\Models\Presence;
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
    ) {}

    public function faireAppel(int $seanceId, array $donneesEleves): void
    {
        if ($this->appelDejaFait($seanceId)) {
            throw new AppelDejaEffectueException($seanceId);
        }

        DB::transaction(function () use ($seanceId, $donneesEleves) {
            foreach ($donneesEleves as $eleveId => $statut) {
                Presence::create([
                    'seance_id' => $seanceId,
                    'eleve_id' => $eleveId,
                    'statut' => $statut,
                    'saisi_par' => Auth::id(),
                ]);
            }

            // La transition exige 'commencee' avant 'dispensee'. On force le
            // passage par 'commencee' si l'enseignant n'a pas cliqué ce bouton
            // avant l'appel, pour ne pas casser le flux "un seul clic" attendu.
            $this->seanceService->marquerCommenceeSiNecessaire($seanceId);
            $this->seanceService->marquerDispensee($seanceId);

            AnomalieAppel::where('seance_id', $seanceId)->update(['resolue' => true]);
        });
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
        Presence::updateOrCreate(
            ['seance_id' => $seanceId, 'eleve_id' => $eleveId],
            ['statut' => $statut, 'saisi_par' => Auth::id()]
        );
    }
}
