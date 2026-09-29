<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Services;

use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Contracts\PaiementServiceContract;
use App\Modules\Scolarite\Exceptions\MontantPaiementInvalideException;
use App\Modules\Scolarite\Exceptions\MontantVersementExcedentaireException;
use App\Modules\Scolarite\Exceptions\MotifAnnulationRequisException;
use App\Modules\Scolarite\Models\FraisEleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\Paiement;
use App\Modules\Scolarite\Models\PaiementAnnulation;
use App\Modules\Scolarite\Models\PaiementRepartition;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaiementService implements PaiementServiceContract
{
    public function __construct(
        private readonly AuditServiceContract $auditService,
        private readonly InscriptionServiceInterface $inscriptionService,
        private readonly ParametrageServiceContract $parametrageService,
        private readonly AnneeScolaireServiceContract $anneeScolaireService,
        private readonly DocumentServiceContract $documentService,
    ) {}

    public function enregistrerPaiement(int $inscriptionId, float $montant, string $mode, ?string $referenceMobileMoney = null): Paiement
    {
        if ($montant <= 0) {
            throw new MontantPaiementInvalideException($montant);
        }

        // Avant toute écriture en base : calcul du reste à payer global de
        // l'inscription. Si le montant saisi le dépasse, rejet intégral —
        // pas d'acceptation partielle, pas de crédit créé (Module 5 §1).
        $resteAPayer = $this->getResteAPayer($inscriptionId);

        if ($montant > $resteAPayer) {
            throw new MontantVersementExcedentaireException($montant, $resteAPayer);
        }

        return DB::transaction(function () use ($inscriptionId, $montant, $mode, $referenceMobileMoney) {
            $inscription = Inscription::with(['eleve', 'classe'])->findOrFail($inscriptionId);

            $numeroRecu = $this->genererNumeroRecu($inscription->annee_scolaire_id);

            $paiement = Paiement::create([
                'inscription_id' => $inscriptionId,
                'montant' => $montant,
                'mode' => $mode,
                'reference_mobile_money' => $referenceMobileMoney,
                'numero_recu' => $numeroRecu,
                'statut' => 'valide',
                'encaisse_par' => Auth::id(),
            ]);

            // Répartition en cascade : frais dus triés par ordre_repartition
            // croissant (1 = inscription, 2 = tranche 1, 3 = tranche 2,
            // 999 = frais divers, départagés par date de création), on
            // impute jusqu'à épuisement du montant.
            $fraisDus = FraisEleve::where('inscription_id', $inscriptionId)
                ->where('statut', 'du')
                ->join('frais', 'frais.id', '=', 'frais_eleve.frais_id')
                ->orderBy('frais.ordre_repartition')
                ->orderBy('frais_eleve.created_at')
                ->select('frais_eleve.*')
                ->get();

            $montantRestant = $montant;

            foreach ($fraisDus as $ligneFrais) {
                if ($montantRestant <= 0) {
                    break;
                }

                $resteSurCetteLigne = $ligneFrais->resteAPayer();
                if ($resteSurCetteLigne <= 0) {
                    continue;
                }

                $alloue = min($montantRestant, $resteSurCetteLigne);

                PaiementRepartition::create([
                    'paiement_id' => $paiement->id,
                    'frais_eleve_id' => $ligneFrais->id,
                    'montant_alloue' => $alloue,
                ]);

                $montantRestant -= $alloue;
            }

            // Archive durable du reçu (DocumentServiceContract).
            $paiement->setRelation('inscription', $inscription);
            $document = $this->documentService->attacherContenu(
                $paiement,
                "Reçu {$paiement->numero_recu}.html",
                view('scolarite::filament.recu-impression', ['paiement' => $paiement])->render(),
                'text/html',
                'recu_versement',
            );
            $paiement->update(['document_recu_id' => $document->id]);

            // Événement système routinier (pas une annulation) : enregistrer(),
            // pas enregistrerAvecMotif() — voir AuditServiceContract.
            $this->auditService->enregistrer(
                $paiement,
                "Versement de {$montant} enregistré pour l'inscription #{$inscriptionId} (reçu {$numeroRecu})",
            );

            // Le rejet se fait avant toute écriture (voir ci-dessus) donc
            // $montantRestant doit toujours atteindre exactement 0 ici — un
            // test doit vérifier cet invariant explicitement.
            if ($this->getResteAPayer($inscriptionId) <= 0.0) {
                $this->inscriptionService->activerApresVersement($inscriptionId);
            }

            return $paiement;
        });
    }

    public function annulerPaiement(int $paiementId, string $motif): void
    {
        if (trim($motif) === '') {
            throw new MotifAnnulationRequisException;
        }

        DB::transaction(function () use ($paiementId, $motif): void {
            $paiement = Paiement::query()->lockForUpdate()->findOrFail($paiementId);

            // Jamais de suppression physique — annulation avec motif
            // uniquement (Module 5 §1). Les paiement_repartitions restent en
            // base pour la traçabilité, mais ne comptent plus dans
            // FraisEleve::montantPaye() une fois le paiement annulé.
            $paiement->update(['statut' => 'annule']);

            PaiementAnnulation::create([
                'paiement_id' => $paiement->id,
                'motif' => $motif,
                'annule_par' => Auth::id(),
                'annule_le' => now(),
            ]);

            // Véritable annulation avec motif obligatoire — enregistrerAvecMotif().
            $this->auditService->enregistrerAvecMotif(
                $paiement,
                "Versement #{$paiementId} annulé : {$motif}",
                $motif,
            );
        });
    }

    public function getTotalPaye(int $inscriptionId): float
    {
        return (float) PaiementRepartition::whereHas(
            'paiement',
            fn ($q) => $q->where('inscription_id', $inscriptionId)->where('statut', 'valide'),
        )->sum('montant_alloue');
    }

    public function getResteAPayer(int $inscriptionId): float
    {
        return (float) FraisEleve::where('inscription_id', $inscriptionId)
            ->where('statut', 'du')
            ->get()
            ->sum(fn (FraisEleve $ligne): float => $ligne->resteAPayer());
    }

    public function getResteParFrais(int $inscriptionId): Collection
    {
        return FraisEleve::where('inscription_id', $inscriptionId)
            ->where('statut', 'du')
            ->with('frais')
            ->get()
            ->map(fn (FraisEleve $ligne): array => [
                'frais_eleve_id' => $ligne->id,
                'frais' => $ligne->frais?->nom,
                'montant' => (float) $ligne->montant,
                'paye' => $ligne->montantPaye(),
                'reste' => $ligne->resteAPayer(),
            ]);
    }

    public function getHistoriquePaiements(int $inscriptionId): Collection
    {
        return Paiement::where('inscription_id', $inscriptionId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Numérotation des reçus déléguée à Socle (ParametrageServiceContract,
     * type 'recu_versement' — voir
     * 2026_09_01_000013_seed_formats_numerotation_scolarite).
     */
    private function genererNumeroRecu(int $anneeScolaireId): string
    {
        $anneeScolaire = $this->anneeScolaireService->getAnneeScolaire($anneeScolaireId);

        return $this->parametrageService->genererNumero('recu_versement', [
            'ANNEE_SCOLAIRE' => $anneeScolaire->libelle,
        ]);
    }
}
