<?php

declare(strict_types=1);

namespace App\Modules\RH\Services;

use App\Modules\RH\Contracts\AvanceSalaireServiceContract;
use App\Modules\RH\Models\AvanceSalaire;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AvanceSalaireService implements AvanceSalaireServiceContract
{
    public function demanderAvance(int $employeId, float $montant, string $motif = '', bool $derogationPlafond = false, ?string $motifDerogation = null): object
    {
        return AvanceSalaire::create([
            'employe_id' => $employeId,
            'montant' => $montant,
            'date_demande' => now()->toDateString(),
            'motif' => $motif,
            'demande_par' => Auth::id(),
            'statut' => 'demande',
            'derogation_plafond' => $derogationPlafond,
            'motif_derogation' => $motifDerogation,
        ]);
    }

    public function approuverAvance(int $avanceId, string $motifValidation): object
    {
        return $this->validerAvance($avanceId, $motifValidation);
    }

    public function validerAvance(int $avanceId, string $motifValidation): object
    {
        $avance = AvanceSalaire::query()->findOrFail($avanceId);
        $avance->update([
            'statut' => 'approuvee',
            'valide_par' => Auth::id(),
            'validee_le' => now(),
            'motif_derogation' => $avance->derogation_plafond ? $motifValidation : $avance->motif_derogation,
        ]);

        return $avance->refresh();
    }

    public function getAvanceActive(int $employeId): ?object
    {
        return AvanceSalaire::query()
            ->where('employe_id', $employeId)
            ->where('statut', 'approuvee')
            ->first();
    }

    public function getAvanceNonRecouvree(int $personnelId, Carbon $periode): ?float
    {
        $avance = $this->getAvanceActive($personnelId);

        return $avance ? $avance->resteADeduire() : null;
    }

    public function enregistrerRecouvrement(int $avanceId, float $montant): void
    {
        $avance = AvanceSalaire::query()->lockForUpdate()->findOrFail($avanceId);
        if ($montant <= 0 || $montant > $avance->resteADeduire()) {
            throw new \DomainException('Montant de recouvrement invalide.');
        }
        $total = (float) $avance->montant_deja_deduit + $montant;
        $avance->update(['montant_deja_deduit' => $total, 'statut' => $total >= (float) $avance->montant ? 'remboursee' : 'approuvee']);
    }
}
