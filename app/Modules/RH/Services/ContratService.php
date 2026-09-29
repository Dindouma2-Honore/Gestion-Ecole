<?php

namespace App\Modules\RH\Services;

use App\Modules\RH\Contracts\ContratServiceInterface;
use App\Modules\RH\Exceptions\ContratChevauchementException;
use App\Modules\RH\Exceptions\ContratDejaResilieException;
use App\Modules\RH\Models\Contrat;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContratService implements ContratServiceInterface
{
    public function __construct(
        private readonly AuditServiceContract $audit
    ) {}

    public function creerContrat(array $donnees): object
    {
        $chevauchement = Contrat::where('employe_id', $donnees['employe_id'])
            ->where('statut', 'actif')
            ->exists();

        if ($chevauchement) {
            throw new ContratChevauchementException($donnees['employe_id']);
        }

        return Contrat::create(array_merge($donnees, ['statut' => 'actif']));
    }

    public function renouveler(int $contratId, \DateTimeInterface $nouvelleDateFin): object
    {
        $contrat = Contrat::findOrFail($contratId);

        if ($contrat->statut === 'resilie') {
            throw new ContratDejaResilieException($contratId);
        }

        $contrat->update([
            'date_fin' => $nouvelleDateFin,
            'statut' => 'actif',
        ]);

        return $contrat;
    }

    public function resilier(int $contratId, \DateTimeInterface $dateEffet, string $motif): void
    {
        DB::transaction(function () use ($contratId, $dateEffet, $motif) {
            $contrat = Contrat::findOrFail($contratId);

            if ($contrat->statut === 'resilie') {
                throw new ContratDejaResilieException($contratId);
            }

            $contrat->update([
                'statut' => 'resilie',
                'date_fin' => $dateEffet,
            ]);

            $this->audit->enregistrerAvecMotif($contrat, "Résiliation du contrat #{$contratId}", $motif);
        });
    }

    public function getContratActif(int $employeId): ?object
    {
        return Contrat::where('employe_id', $employeId)
            ->where('statut', 'actif')
            ->first();
    }

    public function getContratsExpirantBientot(int $joursAvant): Collection
    {
        return Contrat::where('statut', 'actif')
            ->whereNotNull('date_fin')
            ->whereDate('date_fin', '<=', now()->addDays($joursAvant))
            ->get();
    }

    public function estActif(int $contratId): bool
    {
        return Contrat::where('id', $contratId)
            ->where('statut', 'actif')
            ->exists();
    }

    public function getSolde(int $employeId): float
    {
        $contrat = $this->getContratActif($employeId);

        return (float) ($contrat?->salaire_base ?? 0);
    }
}
