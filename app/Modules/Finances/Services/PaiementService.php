<?php

declare(strict_types=1);

namespace App\Modules\Finances\Services;

use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Contracts\FraisScolaireServiceContract;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Exceptions\EleveIntrouvableException;
use App\Modules\Finances\Exceptions\ModePaiementInvalideException;
use App\Modules\Finances\Exceptions\MontantPaiementInvalideException;
use App\Modules\Finances\Exceptions\MotifAnnulationRequisException;
use App\Modules\Finances\Exceptions\PaiementDejaAnnuleException;
use App\Modules\Finances\Exceptions\ReferenceMobileMoneyRequiseException;
use App\Modules\Finances\Models\ConfigurationFraisClasse;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Finances\Models\PaiementTrancheAllocation;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PaiementService implements PaiementServiceContract
{
    private const MODES = ['especes', 'bancaire', 'mobile_money'];

    public function __construct(
        private FraisScolaireServiceContract $fraisScolaire,
        private ParametrageServiceContract $parametrage,
        private AnneeScolaireServiceContract $anneeScolaire,
        private AuditServiceContract $audit,
        private EleveServiceInterface $eleves,
        private CaisseServiceContract $caisse,
        private DocumentServiceContract $documents,
    ) {}

    public function enregistrerPaiement(int $eleveId, float $montant, string $mode, ?string $referenceMobileMoney = null, array $contexteRecu = []): object
    {
        if ($montant <= 0) {
            throw new MontantPaiementInvalideException($montant);
        }

        if (! in_array($mode, self::MODES, true)) {
            throw new ModePaiementInvalideException($mode);
        }

        if ($mode === 'mobile_money' && trim((string) $referenceMobileMoney) === '') {
            throw new ReferenceMobileMoneyRequiseException;
        }

        if (! $this->eleves->existe($eleveId)) {
            throw new EleveIntrouvableException($eleveId);
        }

        $encaisseurId = Auth::id();
        if ($encaisseurId === null) {
            throw new AccessDeniedHttpException('Une authentification est requise pour enregistrer un paiement.');
        }

        return DB::transaction(function () use ($eleveId, $montant, $mode, $referenceMobileMoney, $encaisseurId, $contexteRecu): Paiement {
            $annee = $this->anneeScolaire->getAnneeCourante();

            $paiement = Paiement::create([
                'eleve_id' => $eleveId,
                'annee_scolaire_id' => $annee->id,
                'inscription_id' => $contexteRecu['inscription_id'] ?? null,
                'versement_reference' => $contexteRecu['versement_reference'] ?? null,
                'rubrique' => $contexteRecu['rubrique'] ?? null,
                'frais_divers_eleve_id' => $contexteRecu['frais_divers_eleve_id'] ?? null,
                'montant' => $montant,
                'mode' => $mode,
                'reference_mobile_money' => $mode === 'mobile_money' ? trim((string) $referenceMobileMoney) : null,
                'numero_recu' => $this->parametrage->genererNumero('recu', ['annee' => $annee->libelle]),
                'statut' => 'valide',
                'encaisse_par' => $encaisseurId,
            ]);

            if (($contexteRecu['rubrique'] ?? null) === 'tranches_classe') {
                $this->allouerAuxTranches($paiement, (int) ($contexteRecu['classe_id'] ?? 0));
            }

            $estFraisDivers = filled($contexteRecu['frais_divers_eleve_id'] ?? null) || ($contexteRecu['rubrique'] ?? null) === 'frais_divers';
            $this->caisse->enregistrerMouvement(
                'encaissement',
                $montant,
                $paiement,
                rubrique: (string) ($contexteRecu['libelle_rubrique'] ?? $contexteRecu['rubrique'] ?? 'Paiement scolaire'),
                moduleOrigine: 'Scolarité',
                sousModule: $estFraisDivers ? 'Frais divers' : 'Paiements élèves',
            );

            $pdf = $this->construireRecuPdf($paiement, $annee, $contexteRecu);
            $document = $this->documents->attacherContenu(
                $paiement,
                "recu-{$paiement->numero_recu}.pdf",
                $pdf,
                'application/pdf',
                'recu_paiement',
                'interne',
            );
            $paiement->update(['document_recu_id' => $document->id]);

            return $paiement->refresh();
        });
    }

    private function allouerAuxTranches(Paiement $paiement, int $classeId = 0): void
    {
        $classeId = $classeId > 0
            ? $classeId
            : (int) ($this->eleves->getEleve((int) $paiement->eleve_id)['classe_id'] ?? 0);
        if ($classeId <= 0) {
            return;
        }

        $configuration = ConfigurationFraisClasse::query()->with('tranches')
            ->where('classe_id', $classeId)
            ->where('annee_scolaire_id', $paiement->annee_scolaire_id)
            ->where('actif', true)
            ->first();
        if ($configuration === null) {
            return; // Compatibilité avec les anciennes grilles par niveau.
        }

        $reste = (float) $paiement->montant;
        foreach ($configuration->tranches as $tranche) {
            if ($reste <= 0) {
                break;
            }
            $dejaPaye = (float) PaiementTrancheAllocation::query()
                ->where('tranche_frais_classe_id', $tranche->id)
                ->whereHas('paiement', fn ($query) => $query
                    ->where('eleve_id', $paiement->eleve_id)->where('statut', 'valide'))
                ->sum('montant');
            $allocation = min($reste, max(0.0, (float) $tranche->montant - $dejaPaye));
            if ($allocation > 0) {
                PaiementTrancheAllocation::create([
                    'paiement_id' => $paiement->id,
                    'tranche_frais_classe_id' => $tranche->id,
                    'montant' => round($allocation, 2),
                ]);
                $reste = round($reste - $allocation, 2);
            }
        }
    }

    public function getRecuPdf(int $paiementId): array
    {
        $paiement = Paiement::query()->with('encaisseur')->findOrFail($paiementId);
        if (filled($paiement->versement_reference)) {
            $lignesVersement = Paiement::query()
                ->where('versement_reference', $paiement->versement_reference)
                ->where('statut', 'valide')
                ->get();
            $paiement->montant = $lignesVersement->sum('montant');
        }
        $facture = FacturePreinscription::query()
            ->with('lignes')->where('inscription_id', $paiement->inscription_id)
            ->first();

        return [
            'nom' => "recu-{$paiement->numero_recu}.pdf",
            'contenu' => $this->construireRecuPdf($paiement, contexte: $this->contexteFacture($facture)),
        ];
    }

    /** @param array<string, mixed> $contexte */
    private function construireRecuPdf(Paiement $paiement, ?object $annee = null, array $contexte = []): string
    {
        $annee ??= $this->anneeScolaire->getAnneeCourante();
        $paiement->loadMissing('encaisseur');

        return Pdf::loadView('finances::pdf.recu-paiement', [
            'paiement' => $paiement,
            'eleve' => $this->eleves->getEleve((int) $paiement->eleve_id),
            'annee' => $annee,
            'contexte' => $contexte,
        ])->setPaper('a4')->output();
    }

    /** @return array<string, mixed> */
    private function contexteFacture(?FacturePreinscription $facture): array
    {
        if ($facture === null) {
            return [];
        }

        return [
            'parent_nom' => $facture->parent_nom,
            'facture_reference' => $facture->reference,
            'reference_transaction' => $facture->reference_transaction,
            'statut_inscription' => 'Active',
            'lignes' => $facture->lignes->map(fn ($ligne): array => [
                'libelle' => $ligne->libelle,
                'montant' => (float) $ligne->montant,
            ])->all(),
            'montant_total' => (float) $facture->montant_total,
        ];
    }

    public function annulerPaiement(int $paiementId, string $motif): void
    {
        if (trim($motif) === '') {
            throw new MotifAnnulationRequisException;
        }

        $auteurId = Auth::id();
        if ($auteurId === null) {
            throw new AccessDeniedHttpException('Une authentification est requise pour annuler un paiement.');
        }

        DB::transaction(function () use ($paiementId, $motif, $auteurId): void {
            $paiement = Paiement::query()->lockForUpdate()->findOrFail($paiementId);

            if ($paiement->statut === 'annule') {
                throw new PaiementDejaAnnuleException($paiementId);
            }

            $paiement->update(['statut' => 'annule']);
            $paiement->annulations()->create([
                'motif' => trim($motif),
                'annule_par' => $auteurId,
                'annule_le' => now(),
            ]);

            $this->audit->enregistrerAvecMotif($paiement, "Annulation du paiement #{$paiementId}", trim($motif));
        });
    }

    public function getTotalPaye(int $eleveId, int $anneeScolaireId): float
    {
        return round((float) Paiement::query()
            ->where('eleve_id', $eleveId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('statut', 'valide')
            ->sum('montant'), 2);
    }

    public function getResteAPayer(int $eleveId, int $anneeScolaireId): float
    {
        return max(0.0, round(
            $this->fraisScolaire->getMontantDu($eleveId, $anneeScolaireId)
            - $this->getTotalPaye($eleveId, $anneeScolaireId),
            2,
        ));
    }

    public function getHistoriquePaiements(int $eleveId): Collection
    {
        return Paiement::query()
            ->where('eleve_id', $eleveId)
            ->latest()
            ->get()
            ->map(fn (Paiement $paiement): array => [
                'id' => $paiement->id,
                'numero_recu' => $paiement->numero_recu,
                'montant' => (float) $paiement->montant,
                'mode' => $paiement->mode,
                'statut' => $paiement->statut,
                'rubrique' => $paiement->rubrique,
                'versement_reference' => $paiement->versement_reference,
                'date' => $paiement->created_at,
                'document_recu_id' => $paiement->document_recu_id,
                'url_recu' => $this->documents->getUrlTelechargement($paiement->document_recu_id),
            ]);
    }
}
