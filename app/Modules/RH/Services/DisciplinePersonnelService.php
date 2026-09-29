<?php

namespace App\Modules\RH\Services;

use App\Modules\RH\Contracts\DisciplinePersonnelServiceContract;
use App\Modules\RH\Exceptions\DureeSuspensionRequiseException;
use App\Modules\RH\Exceptions\SanctionSansMotifException;
use App\Modules\RH\Models\SanctionPersonnel;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class DisciplinePersonnelService implements DisciplinePersonnelServiceContract
{
    public function __construct(
        private readonly AuditServiceContract $audit
    ) {}

    public function enregistrerSanction(int $employeId, string $type, string $motif, ?int $dureeJours = null): object
    {
        if (empty(trim($motif))) {
            throw new SanctionSansMotifException;
        }

        if ($type === 'suspension' && is_null($dureeJours)) {
            throw new DureeSuspensionRequiseException;
        }

        $sanction = SanctionPersonnel::create([
            'employe_id' => $employeId,
            'type' => $type,
            'motif' => $motif,
            'duree_jours' => $dureeJours,
            'date_sanction' => now(),
            'statut' => 'en_attente_validation',
            'created_by' => Auth::id() ?? 1,
        ]);

        $this->audit->enregistrerAvecMotif($sanction, "Sanction {$type} créée pour l'employé #{$employeId}", $motif);

        return $sanction;
    }

    public function valider(int $sanctionId, int $validateurId): void
    {
        SanctionPersonnel::where('id', $sanctionId)->update([
            'statut' => 'validee',
            'valide_par' => $validateurId,
        ]);
    }

    public function annuler(int $sanctionId, string $motifAnnulation): void
    {
        $sanction = SanctionPersonnel::findOrFail($sanctionId);
        $sanction->update(['statut' => 'annulee']);

        $this->audit->enregistrerAvecMotif($sanction, "Annulation de la sanction #{$sanctionId}", $motifAnnulation);
    }

    public function estSuspenduA(int $employeId, \DateTimeInterface $date): bool
    {
        return SanctionPersonnel::where('employe_id', $employeId)
            ->where('type', 'suspension')
            ->where('statut', 'validee')
            ->where('date_sanction', '<=', $date)
            ->get()
            ->contains(fn (SanctionPersonnel $sanction): bool => $sanction->date_sanction
                ->copy()
                ->addDays((int) $sanction->duree_jours)
                ->startOfDay()
                ->greaterThanOrEqualTo($date));
    }

    public function getHistoriqueSanctions(int $employeId): Collection
    {
        return SanctionPersonnel::where('employe_id', $employeId)
            ->orderByDesc('date_sanction')
            ->get();
    }
}
