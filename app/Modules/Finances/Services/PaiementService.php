<?php

declare(strict_types=1);

namespace App\Modules\Finances\Services;

use App\Modules\Communication\Services\WhatsAppDispatcher;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Contracts\ConfigurationFraisClasseServiceContract;
use App\Modules\Finances\Contracts\FacturePreinscriptionServiceContract;
use App\Modules\Finances\Contracts\FraisScolaireServiceContract;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Exceptions\EleveIntrouvableException;
use App\Modules\Finances\Exceptions\ModePaiementInvalideException;
use App\Modules\Finances\Exceptions\MontantPaiementInvalideException;
use App\Modules\Finances\Exceptions\MotifAnnulationRequisException;
use App\Modules\Finances\Exceptions\PaiementDejaAnnuleException;
use App\Modules\Finances\Exceptions\ReferenceMobileMoneyRequiseException;
use App\Modules\Finances\Models\CatalogueFraisDivers;
use App\Modules\Finances\Models\ConfigurationFraisClasse;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Finances\Models\PaiementTrancheAllocation;
use App\Modules\Finances\Support\RemiseScolariteCalculator;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Scolarite\Contracts\InscriptionFinanceReadContract;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use App\Support\SafeFilename;
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
        private InscriptionFinanceReadContract $inscriptions,
    ) {}

    public function enregistrerPaiement(int $inscriptionId, float $montant, string $mode, ?string $referenceMobileMoney = null, array $contexteRecu = []): object
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

        $inscription = $this->inscriptions->getInfoInscriptionParId($inscriptionId);
        if (! $inscription || ! in_array($inscription['statut'], ['active', 'validee', 'en_attente_versement'], true)) {
            throw new \DomainException('Le versement doit être rattaché à une inscription existante et valide.');
        }
        $eleveId = (int) $inscription['eleve_id'];

        $encaisseurId = Auth::id();
        if ($encaisseurId === null) {
            throw new AccessDeniedHttpException('Une authentification est requise pour enregistrer un paiement.');
        }

        return DB::transaction(function () use ($inscriptionId, $inscription, $eleveId, $montant, $mode, $referenceMobileMoney, $encaisseurId, $contexteRecu): Paiement {
            $anneeId = (int) $inscription['annee_scolaire_id'];
            $annee = $this->anneeScolaire->getAnneeScolaire($anneeId);

            $paiement = Paiement::create([
                'eleve_id' => $eleveId,
                'annee_scolaire_id' => $anneeId,
                'inscription_id' => $inscriptionId,
                'versement_reference' => $contexteRecu['versement_reference'] ?? null,
                'rubrique' => $contexteRecu['rubrique'] ?? null,
                'frais_divers_eleve_id' => $contexteRecu['frais_divers_eleve_id'] ?? null,
                'montant' => $montant,
                'mode' => $mode,
                'reference_mobile_money' => $mode === 'mobile_money' ? trim((string) $referenceMobileMoney) : null,
                'numero_recu' => $this->genererNumeroRecu($annee->libelle),
                'statut' => 'valide',
                'encaisse_par' => $encaisseurId,
            ]);

            if (($contexteRecu['rubrique'] ?? null) === 'tranches_classe') {
                $this->allouerAuxTranches($paiement, (int) $inscription['classe_id']);
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

            $contexteRecu['repartition'] = $this->repartitionVersement(collect([
                $paiement->load(['allocationsTranches.tranche', 'fraisDivers.poste']),
            ]));
            $pdf = $this->construireRecuPdf($paiement, $annee, $contexteRecu);
            $document = $this->documents->attacherContenu(
                $paiement,
                SafeFilename::make("recu-{$paiement->numero_recu}.pdf"),
                $pdf,
                'application/pdf',
                'recu_paiement',
                'interne',
            );
            $paiement->update(['document_recu_id' => $document->id]);
            DB::afterCommit(function () use ($paiement): void {
                try {
                    app(WhatsAppDispatcher::class)->dispatchPaiementRecu($paiement->refresh());
                } catch (\Throwable $exception) {
                    report($exception);
                    logger()->warning('Échec de mise en file du reçu de paiement scolaire sur WhatsApp.', [
                        'paiement_id' => $paiement->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });

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

        $montantNet = RemiseScolariteCalculator::montantNet(
            (int) $paiement->eleve_id,
            (int) $paiement->annee_scolaire_id,
            (float) $configuration->montant_total,
        );
        $coefficient = (float) $configuration->montant_total > 0
            ? $montantNet / (float) $configuration->montant_total
            : 0.0;
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
            $montantTranche = round((float) $tranche->montant * $coefficient, 2);
            $allocation = min($reste, max(0.0, $montantTranche - $dejaPaye));
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
        $paiement = Paiement::query()->where('paiements.id', $paiementId)->firstOrFail();

        $inscriptionData = app(InscriptionFinanceReadContract::class)->getInfoInscription(
            $paiement->inscription_id,
            (int) $paiement->eleve_id,
            (int) $paiement->annee_scolaire_id
        );

        if (filled($paiement->versement_reference)) {
            $lignesVersement = Paiement::query()
                ->with(['allocationsTranches.tranche', 'fraisDivers.poste'])
                ->where('versement_reference', $paiement->versement_reference)
                ->where('statut', 'valide')
                ->get();
            $paiement->montant = $lignesVersement->sum('montant');
        } else {
            $lignesVersement = collect([$paiement->load(['allocationsTranches.tranche', 'fraisDivers.poste'])]);
        }

        $facture = FacturePreinscription::query()
            ->with('lignes')->where('inscription_id', $paiement->inscription_id)
            ->first();
        $contexte = $this->contexteFacture($facture);
        $contexte['repartition'] = $this->repartitionVersement($lignesVersement);
        $contexte = $this->preparerContexteRecu($paiement, $contexte);

        return [
            'nom' => SafeFilename::make("recu-{$paiement->numero_recu}.pdf"),
            'contenu' => $this->construireRecuPdf($paiement, contexte: $contexte),
        ];
    }

    /** @param array<string, mixed> $contexte */
    private function construireRecuPdf(Paiement $paiement, ?object $annee = null, array $contexte = []): string
    {
        $annee ??= $this->anneeScolaire->getAnneeCourante();
        $paiement->loadMissing('encaisseur');

        $contexte = $this->preparerContexteRecu($paiement, $contexte);
        $eleve = $this->eleves->getEleve((int) $paiement->eleve_id);

        return Pdf::loadView('finances::pdf.recu-paiement', [
            'paiement' => $paiement,
            'eleve' => $eleve,
            'annee' => $annee,
            'contexte' => $contexte,
        ])->setPaper('a4')->output();
    }

    /**
     * Prépare un contexte financier strictement homogène avec la situation globale de l'élève.
     *
     * @param  array<string, mixed>  $contexte
     * @return array<string, mixed>
     */
    private function preparerContexteRecu(Paiement $paiement, array $contexte = []): array
    {
        $eleveId = (int) $paiement->eleve_id;
        $anneeId = (int) ($paiement->annee_scolaire_id ?: $this->anneeScolaire->getAnneeCouranteId());

        $inscription = null;
        $inscriptionData = app(InscriptionFinanceReadContract::class)->getInfoInscription($paiement->inscription_id, $eleveId, $anneeId);
        if ($inscriptionData) {
            $inscription = (object) [
                'id' => $inscriptionData['id'],
                'classe_id' => $inscriptionData['classe_id'],
                'eleve_id' => $inscriptionData['eleve_id'],
                'annee_scolaire_id' => $inscriptionData['annee_scolaire_id'],
                'classe' => $inscriptionData['classe'],
                'anneeScolaire' => $inscriptionData['anneeScolaire'],
                'eleve' => $inscriptionData['eleve'],
                'parent_principal_nom' => $inscriptionData['parent_principal_nom'],
                'parent_principal_telephone' => $inscriptionData['parent_principal_telephone'],
            ];
        }

        $eleve = $this->eleves->getEleve($eleveId);
        $classeId = (int) ($inscription?->classe_id ?? ($eleve['classe_id'] ?? 0));

        $contexte['parent_nom'] = $inscription?->parent_principal_nom ?: ($contexte['parent_nom'] ?? ($eleve['parent_nom'] ?? 'Non renseigné'));
        $contexte['parent_telephone'] = $inscription?->parent_principal_telephone ?: ($contexte['parent_telephone'] ?? ($eleve['parent_telephone'] ?? null));
        $contexte['classe_nom'] = $inscription?->classe?->nom ?: ($eleve['classe_nom'] ?? '—');
        $contexte['matricule'] = $inscription?->eleve?->matricule_permanent ?: ($eleve['matricule_permanent'] ?? '—');
        $contexte['annee_libelle'] = $inscription?->anneeScolaire?->libelle ?: ($this->anneeScolaire->getAnneeCourante()?->libelle ?? '—');

        $montantInscription = (float) config('scolarite.frais_inscription', 15_000);
        $totalDivers = 0.0;
        $totalVersements = 0.0;

        if ($inscription) {
            $fraisFacturation = app(FacturePreinscriptionServiceContract::class);
            $totalDivers = (float) collect($fraisFacturation->getFraisDiversSouscrits($inscription->id))->sum('montant');
            $totalVersements = (float) collect($fraisFacturation->getVersements($inscription->id))->sum('montant');
        }

        $montantScolarite = 0.0;
        if ($classeId > 0 && $anneeId > 0) {
            try {
                $scolariteData = app(ConfigurationFraisClasseServiceContract::class)
                    ->situation($eleveId, $classeId, $anneeId);
                $montantScolarite = (float) ($scolariteData['montant_total'] ?? 0);
            } catch (\Throwable) {
                $config = ConfigurationFraisClasse::query()
                    ->where('classe_id', $classeId)
                    ->where('annee_scolaire_id', $anneeId)
                    ->where('actif', true)
                    ->first();
                if ($config) {
                    $montantScolarite = RemiseScolariteCalculator::montantNet($eleveId, $anneeId, (float) $config->montant_total);
                }
            }
        }

        $totalAnnuel = round($montantInscription + $totalDivers + $montantScolarite, 2);
        if ($totalAnnuel <= 0 && ! empty($contexte['montant_total'])) {
            $totalAnnuel = (float) $contexte['montant_total'];
        }
        if ($totalAnnuel <= 0) {
            $totalAnnuel = (float) $paiement->montant;
        }

        $totalPayeCumule = $totalVersements > 0
            ? $totalVersements
            : (float) Paiement::query()
                ->where('eleve_id', $eleveId)
                ->where('annee_scolaire_id', $anneeId)
                ->where('statut', 'valide')
                ->sum('montant');

        if ($totalPayeCumule <= 0) {
            $totalPayeCumule = (float) $paiement->montant;
        }

        $soldeRestant = max(0.0, round($totalAnnuel - $totalPayeCumule, 2));

        $contexte['montant_total'] = $totalAnnuel;
        $contexte['total_paye'] = $totalPayeCumule;
        $contexte['solde_restant'] = $soldeRestant;
        $contexte['est_solde'] = ($soldeRestant <= 0.0);

        return $contexte;
    }

    /** @return array<string, mixed> */
    private function contexteFacture(?FacturePreinscription $facture): array
    {
        if ($facture === null) {
            return [];
        }

        return [
            'parent_nom' => $facture->parent_nom,
            'parent_telephone' => $facture->parent_telephone,
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

    /** @return array<int, array{libelle: string, montant: float}> */
    private function repartitionVersement(Collection $paiements): array
    {
        return $paiements->flatMap(function (Paiement $paiement): array {
            if ($paiement->rubrique === 'tranches_classe' && $paiement->allocationsTranches->isNotEmpty()) {
                return $paiement->allocationsTranches->map(fn (PaiementTrancheAllocation $allocation): array => [
                    'libelle' => $allocation->tranche?->libelle ? "Scolarité — {$allocation->tranche->libelle}" : 'Tranche de scolarité',
                    'montant' => (float) $allocation->montant,
                ])->all();
            }

            $libelle = match (true) {
                $paiement->rubrique === 'inscription' => "Frais d'inscription",
                str_starts_with((string) $paiement->rubrique, 'divers:') => $paiement->fraisDivers?->poste?->nom
                    ?? CatalogueFraisDivers::find((int) str_replace('divers:', '', (string) $paiement->rubrique))?->nom
                    ?? 'Prestation annexe / Frais divers',
                $paiement->rubrique === 'tranches_classe' => 'Frais de scolarité',
                default => str($paiement->rubrique ?: 'Paiement scolaire')->replace('_', ' ')->headline()->toString(),
            };

            return [[
                'libelle' => $libelle,
                'montant' => (float) $paiement->montant,
            ]];
        })->values()->all();
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

    private function genererNumeroRecu(string $anneeLibelle): string
    {
        do {
            $numero = $this->parametrage->genererNumero('recu', ['annee' => $anneeLibelle]);
        } while (Paiement::where('numero_recu', $numero)->exists());

        return $numero;
    }
}
