<?php

namespace App\Modules\RH\Services;

use App\Modules\RH\Contracts\PrimeServiceContract;
use App\Modules\RH\Exceptions\TypePrimeInexistantException;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\PersonnelPrime;
use App\Modules\RH\Models\Prime;
use App\Modules\RH\Models\TypePrime;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class PrimeService implements PrimeServiceContract
{
    public function __construct(
        private readonly AuditServiceContract $audit
    ) {}

    public function proposerPrime(int $employeId, string $typePrimeCode, float $montant, int $mois, int $annee, ?string $justification = null): object
    {
        $type = TypePrime::where('code', $typePrimeCode)->first();

        if (! $type) {
            throw new TypePrimeInexistantException($typePrimeCode);
        }

        return Prime::create([
            'employe_id' => $employeId,
            'type_prime_id' => $type->id,
            'montant' => $montant,
            'mois' => $mois,
            'annee' => $annee,
            'justification' => $justification,
            'statut' => 'proposee',
            'proposee_par' => Auth::id() ?? 1,
        ]);
    }

    public function valider(int $primeId, int $validateurId): void
    {
        $prime = Prime::findOrFail($primeId);
        $prime->update([
            'statut' => 'validee',
            'validee_par' => $validateurId,
        ]);
        $this->audit->enregistrerAvecMotif($prime, "Validation de la prime #{$primeId}", 'Prime validée par le responsable habilité');
    }

    public function rejeter(int $primeId, string $motif): void
    {
        $prime = Prime::findOrFail($primeId);
        $prime->update(['statut' => 'rejetee']);

        $this->audit->enregistrerAvecMotif($prime, "Rejet de la prime #{$primeId}", $motif);
    }

    public function getPrimesDuMois(int $employeId, int $mois, int $annee): Collection
    {
        return Prime::where('employe_id', $employeId)
            ->where('mois', $mois)
            ->where('annee', $annee)
            ->where('statut', 'validee')
            ->with('typePrime')
            ->get()
            ->map(fn ($p) => (object) [
                'prime_id' => $p->id,
                'libelle' => $p->typePrime?->libelle ?? 'Prime',
                'montant' => (float) $p->montant,
                'date_reference' => $p->created_at?->toDateString(),
            ]);
    }

    public function marquerPrimesIntegrees(array $primeIds, int $bulletinId): void
    {
        if ($primeIds === []) {
            return;
        }

        // Garde sur le statut : une prime déjà intégrée à un autre bulletin n'est jamais ré-affectée.
        Prime::query()
            ->whereIn('id', $primeIds)
            ->where('statut', 'validee')
            ->update(['statut' => 'integree_paie', 'bulletin_paie_id' => $bulletinId]);
    }

    public function getPrimesActives(int $personnelId, Carbon $periode): Collection
    {
        $contrat = Contrat::query()->where('employe_id', $personnelId)->where('statut', 'actif')->latest('date_debut')->first();
        if (! $contrat) {
            return collect();
        }

        $recurrentes = PersonnelPrime::query()->with('typePrime')->where('employe_id', $personnelId)->where('actif', true)
            ->whereDate('date_attribution', '<=', $periode->copy()->endOfMonth())
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $periode->copy()->startOfMonth()))->get()
            ->map(function (PersonnelPrime $prime) use ($contrat): object {
                $valeur = (float) ($prime->valeur_override ?? $prime->typePrime->valeur_defaut ?? 0);
                $pourcentage = in_array($prime->typePrime->mode_calcul, ['pourcentage', 'pourcentage_base', 'pourcentage_salaire'], true);

                return (object) [
                    'libelle' => $prime->typePrime->libelle,
                    'montant' => $pourcentage ? round($valeur / 100 * (float) $contrat->salaire_base, 2) : $valeur,
                    'date_reference' => $prime->date_attribution?->toDateString(),
                ];
            });

        return $recurrentes->concat($this->getPrimesDuMois($personnelId, $periode->month, $periode->year));
    }

    public function getPrimesPourPeriode(int $personnelId, Carbon $debut, Carbon $fin): Collection
    {
        $contrat = Contrat::query()->where('employe_id', $personnelId)
            ->where('statut', 'actif')->latest('date_debut')->first();
        if (! $contrat) {
            return collect();
        }

        $recurrentes = PersonnelPrime::query()->with('typePrime')
            ->where('employe_id', $personnelId)->where('actif', true)
            ->whereDate('date_attribution', '<=', $fin)
            ->where(fn ($query) => $query->whereNull('date_fin')->orWhereDate('date_fin', '>=', $debut))
            ->get()
            ->map(function (PersonnelPrime $prime) use ($contrat): object {
                $valeur = (float) ($prime->valeur_override ?? $prime->typePrime?->valeur_defaut ?? 0);
                $pourcentage = in_array($prime->typePrime?->mode_calcul, ['pourcentage', 'pourcentage_base', 'pourcentage_salaire'], true);

                return (object) [
                    'libelle' => $prime->typePrime?->libelle ?? 'Prime',
                    'montant' => $pourcentage ? round($valeur / 100 * (float) $contrat->salaire_base, 2) : $valeur,
                    'date_reference' => $prime->date_attribution?->toDateString(),
                ];
            });

        $mois = [];
        $curseur = $debut->copy()->startOfMonth();
        while ($curseur->lte($fin)) {
            $mois[] = [$curseur->month, $curseur->year];
            $curseur->addMonth();
        }
        $ponctuelles = Prime::query()->with('typePrime')->where('employe_id', $personnelId)
            ->where('statut', 'validee')
            ->where(function ($query) use ($mois): void {
                foreach ($mois as [$moisNumero, $annee]) {
                    $query->orWhere(fn ($subQuery) => $subQuery->where('mois', $moisNumero)->where('annee', $annee));
                }
            })->get()
            ->map(fn (Prime $prime): object => (object) [
                'prime_id' => $prime->id,
                'libelle' => $prime->typePrime?->libelle ?? 'Prime',
                'montant' => (float) $prime->montant,
                'date_reference' => $prime->created_at?->toDateString(),
            ]);

        return $recurrentes->concat($ponctuelles);
    }
}
