<?php

declare(strict_types=1);

namespace App\Modules\Finances\Services;

use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Exceptions\SessionCaisseDejaOuverteException;
use App\Modules\Finances\Exceptions\SessionCaisseIntrouvableException;
use App\Modules\Finances\Models\MouvementCaisse;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Finances\Models\SessionCaisse;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CaisseService implements CaisseServiceContract
{
    public function __construct(
        private readonly AuditServiceContract $audit,
    ) {}

    public function ouvrirSession(float $soldeOuverture): object
    {
        $date = now()->format('Y-m-d');

        if (SessionCaisse::where('statut', 'ouverte')->exists() || SessionCaisse::whereDate('date_session', $date)->exists()) {
            throw new SessionCaisseDejaOuverteException($date);
        }

        return SessionCaisse::create([
            'date_session' => $date,
            'solde_ouverture' => $soldeOuverture,
            'statut' => 'ouverte',
            'ouverte_par' => Auth::id(),
        ]);
    }

    public function garantirSessionOuverte(): object
    {
        if ($session = SessionCaisse::query()->where('statut', 'ouverte')->oldest('date_session')->oldest('id')->first()) {
            return $session;
        }

        $session = SessionCaisse::query()->whereDate('date_session', now()->toDateString())->first();
        if ($session !== null) {
            return $session;
        }

        $sessionPrecedente = SessionCaisse::query()
            ->whereDate('date_session', '<', now()->toDateString())
            ->latest('date_session')
            ->latest('id')
            ->first();
        $soldeReporte = $sessionPrecedente ? $this->soldeFinal($sessionPrecedente) : 0.0;

        return SessionCaisse::firstOrCreate(['date_session' => now()->toDateString()], [
            'solde_ouverture' => $soldeReporte,
            'statut' => 'ouverte',
            'ouverte_par' => Auth::id(),
        ]);
    }

    private function soldeFinal(SessionCaisse $session): float
    {
        if ($session->solde_cloture_reel !== null) {
            return round((float) $session->solde_cloture_reel, 2);
        }

        $encaissements = (float) $this->mouvementsEspeces($session->id)
            ->where('type', 'encaissement')
            ->sum('montant');
        $decaissements = (float) $this->mouvementsEspeces($session->id)
            ->where('type', 'decaissement')
            ->sum('montant');

        return round((float) $session->solde_ouverture + $encaissements - $decaissements, 2);
    }

    public function enregistrerMouvement(string $type, float $montant, ?Model $source = null, ?string $justificatif = null, ?string $rubrique = null, ?string $moduleOrigine = null, ?string $sousModule = null): object
    {
        return DB::transaction(function () use ($type, $montant, $source, $justificatif, $rubrique, $moduleOrigine, $sousModule): MouvementCaisse {
            $session = $this->getSessionOuverte();

            return MouvementCaisse::create([
                'session_caisse_id' => $session->id,
                'type' => $type,
                'rubrique' => $rubrique ?? 'Non classé',
                'montant' => $montant,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'module_origine' => $moduleOrigine,
                'sous_module' => $sousModule,
                'reference_type' => $source ? class_basename($source) : null,
                'reference_id' => $source?->getKey(),
                'justificatif' => $justificatif,
            ]);
        });
    }

    public function cloturerSession(float $soldeReel): object
    {
        return DB::transaction(function () use ($soldeReel): SessionCaisse {
            $session = $this->getSessionOuverte(false);
            $soldeTheorique = $this->soldeFinal($session);
            $ecart = round($soldeReel - $soldeTheorique, 2);

            $session->update([
                'solde_cloture_theorique' => $soldeTheorique,
                'solde_cloture_reel' => $soldeReel,
                'ecart' => $ecart,
                'statut' => 'cloturee',
                'cloturee_par' => Auth::id(),
            ]);

            if (abs($ecart) > 0) {
                // Signale l'écart à la Direction — au-delà d'un certain
                // seuil, pourrait déclencher une alerte automatique.
                $this->audit->enregistrerAvecMotif(
                    $session,
                    'Clôture de caisse avec écart',
                    "Écart constaté : {$ecart} FCFA",
                );
            }

            return $session;
        });
    }

    public function getSoldeTheoriqueActuel(): float
    {
        $session = $this->getSessionOuverte(false);

        return $this->soldeFinal($session);
    }

    private function mouvementsEspeces(int $sessionId): Builder
    {
        return MouvementCaisse::query()->where('session_caisse_id', $sessionId)
            ->whereNotExists(function (\Illuminate\Database\Query\Builder $query): void {
                $query->selectRaw('1')->from('paiements')
                    ->whereColumn('paiements.id', 'mouvements_caisse.source_id')
                    ->where('mouvements_caisse.source_type', (new Paiement)->getMorphClass())
                    ->whereIn('paiements.mode', ['bancaire', 'mobile_money']);
            });
    }

    public function getBilanParRubrique(int $sessionId): array
    {
        $mouvements = MouvementCaisse::query()->where('session_caisse_id', $sessionId)->get();
        $encaissements = $mouvements->where('type', 'encaissement')->groupBy('rubrique')
            ->map(fn ($lignes): float => round((float) $lignes->sum('montant'), 2))->all();
        $decaissements = $mouvements->where('type', 'decaissement')->groupBy('rubrique')
            ->map(fn ($lignes): float => round((float) $lignes->sum('montant'), 2))->all();
        $totalEncaissements = round(array_sum($encaissements), 2);
        $totalDecaissements = round(array_sum($decaissements), 2);

        return [
            'encaissements' => $encaissements,
            'decaissements' => $decaissements,
            'total_encaissements' => $totalEncaissements,
            'total_decaissements' => $totalDecaissements,
            'solde_jour' => round($totalEncaissements - $totalDecaissements, 2),
        ];
    }

    private function getSessionOuverte(bool $ouvrirSiAbsente = true): SessionCaisse
    {
        $session = SessionCaisse::query()->where('statut', 'ouverte')
            ->oldest('date_session')->oldest('id')->lockForUpdate()->first();
        if ($session) {
            return $session;
        }
        if ($ouvrirSiAbsente) {
            $candidate = $this->garantirSessionOuverte();
            $session = SessionCaisse::query()->where('statut', 'ouverte')->lockForUpdate()->find($candidate->id);
            if ($session) {
                return $session;
            }
        }
        throw new SessionCaisseIntrouvableException;
    }
}
