<?php

namespace App\Modules\RH\Services;

use App\Modules\RH\Contracts\FormationServiceContract;
use App\Modules\RH\Exceptions\ParticipantDejaInscritException;
use App\Modules\RH\Models\Formation;
use App\Modules\RH\Models\FormationParticipant;
use Illuminate\Support\Collection;

class FormationService implements FormationServiceContract
{
    public function creerFormation(array $donnees): object
    {
        return Formation::create($donnees);
    }

    public function inscrireParticipant(int $formationId, int $employeId): void
    {
        $dejaInscrit = FormationParticipant::where('formation_id', $formationId)
            ->where('employe_id', $employeId)
            ->exists();

        if ($dejaInscrit) {
            throw new ParticipantDejaInscritException($employeId, $formationId);
        }

        FormationParticipant::create([
            'formation_id' => $formationId,
            'employe_id' => $employeId,
        ]);
    }

    public function marquerPresence(int $formationId, int $employeId, bool $present): void
    {
        FormationParticipant::where('formation_id', $formationId)
            ->where('employe_id', $employeId)
            ->update(['present' => $present]);
    }

    public function getHistoriqueFormations(int $employeId): Collection
    {
        return FormationParticipant::where('employe_id', $employeId)
            ->with('formation')
            ->where('present', true)
            ->get();
    }

    public function genererAttestation(int $formationId, int $employeId): object
    {
        $participant = FormationParticipant::where('formation_id', $formationId)
            ->where('employe_id', $employeId)
            ->with(['formation', 'employe'])
            ->firstOrFail();

        return (object) [
            'attestation_id' => "ATT-{$formationId}-{$employeId}",
            'formation' => $participant->formation->titre,
            'employe' => $participant->employe->nom_complet,
            'date_fin' => $participant->formation->date_fin,
        ];
    }
}
