<?php

declare(strict_types=1);

namespace App\Modules\Finances\Services;

use App\Modules\Finances\Contracts\ConfigurationFraisClasseServiceContract;
use App\Modules\Finances\Contracts\FraisScolaireServiceContract;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Contracts\RepartitionPaiementServiceContract;
use App\Modules\Finances\Exceptions\MontantPaiementInvalideException;
use App\Modules\Finances\Exceptions\VersementSuperieurAuDuException;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Finances\Models\FraisDiversEleve;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RepartitionPaiementService implements RepartitionPaiementServiceContract
{
    public function __construct(
        private readonly InscriptionServiceInterface $inscriptions,
        private readonly FraisScolaireServiceContract $frais,
        private readonly PaiementServiceContract $paiements,
        private readonly ConfigurationFraisClasseServiceContract $configurationsClasse,
    ) {}

    public function repartir(int $inscriptionId, float $montantVerse, string $mode, ?string $referenceMobileMoney = null): array
    {
        if ($montantVerse <= 0) {
            throw new MontantPaiementInvalideException($montantVerse);
        }

        return DB::transaction(function () use ($inscriptionId, $montantVerse, $mode, $referenceMobileMoney): array {
            $contexte = $this->inscriptions->getContexteFinancier($inscriptionId);
            $configuration = $this->configurationsClasse->obtenir($contexte['classe_id'], $contexte['annee_scolaire_id']);
            if ($configuration !== null) {
                $situation = $this->configurationsClasse->situation(
                    $contexte['eleve_id'], $contexte['classe_id'], $contexte['annee_scolaire_id']
                );
                $fraisInscription = (float) config('scolarite.frais_inscription', 15_000);
                $inscriptionPayee = (float) Paiement::query()
                    ->where('inscription_id', $inscriptionId)
                    ->where('rubrique', 'inscription')
                    ->where('statut', 'valide')
                    ->sum('montant');
                $resteInscription = max(0.0, $fraisInscription - $inscriptionPayee);
                $facture = FacturePreinscription::query()->with('lignes')->where('inscription_id', $inscriptionId)->first();
                $fraisDiversIds = $facture?->lignes->pluck('type_frais')
                    ->filter(fn (string $type): bool => str_starts_with($type, 'divers-'))
                    ->map(fn (string $type): int => (int) Str::after($type, 'divers-'))
                    ->values() ?? collect();
                $fraisDivers = FraisDiversEleve::query()
                    ->with('poste')
                    ->where('eleve_id', $contexte['eleve_id'])
                    ->where('annee_scolaire_id', $contexte['annee_scolaire_id'])
                    ->where('statut', 'actif')
                    ->whereIn('catalogue_frais_divers_id', $fraisDiversIds)
                    ->get();
                $resteDivers = $fraisDivers->sum(function (FraisDiversEleve $frais): float {
                    $paye = (float) Paiement::query()
                        ->where('frais_divers_eleve_id', $frais->id)
                        ->where('statut', 'valide')
                        ->sum('montant');

                    return max(0.0, (float) $frais->montant - $paye);
                });
                $resteDu = round($resteInscription + $resteDivers + $situation['reste_a_payer'], 2);
                if ($montantVerse > $resteDu + 0.001) {
                    throw new VersementSuperieurAuDuException($montantVerse, $resteDu);
                }
                $reference = (string) Str::uuid();
                $restant = $montantVerse;
                $lignes = [];

                $affecteInscription = min($restant, $resteInscription);
                if ($affecteInscription > 0) {
                    $lignes[] = $this->paiements->enregistrerPaiement(
                        $contexte['eleve_id'], $affecteInscription, $mode, $referenceMobileMoney,
                        ['inscription_id' => $inscriptionId, 'versement_reference' => $reference,
                            'rubrique' => 'inscription', 'libelle_rubrique' => "Frais d'inscription"],
                    );
                    $restant = round($restant - $affecteInscription, 2);
                }

                foreach ($fraisDivers as $frais) {
                    if ($restant <= 0) {
                        break;
                    }
                    $dejaPaye = (float) Paiement::query()->where('frais_divers_eleve_id', $frais->id)->where('statut', 'valide')->sum('montant');
                    $affecte = min($restant, max(0.0, (float) $frais->montant - $dejaPaye));
                    if ($affecte > 0) {
                        $lignes[] = $this->paiements->enregistrerPaiement(
                            $contexte['eleve_id'], $affecte, $mode, $referenceMobileMoney,
                            ['inscription_id' => $inscriptionId, 'versement_reference' => $reference,
                                'rubrique' => 'divers:'.$frais->id, 'libelle_rubrique' => $frais->poste->nom,
                                'frais_divers_eleve_id' => $frais->id],
                        );
                        $restant = round($restant - $affecte, 2);
                    }
                }

                if ($restant > 0) {
                    $lignes[] = $this->paiements->enregistrerPaiement(
                        $contexte['eleve_id'], $restant, $mode, $referenceMobileMoney,
                        ['inscription_id' => $inscriptionId, 'versement_reference' => $reference,
                            'classe_id' => $contexte['classe_id'], 'rubrique' => 'tranches_classe',
                            'libelle_rubrique' => 'Frais scolaires par tranches'],
                    );
                }

                return ['versement_reference' => $reference, 'montant' => $montantVerse, 'lignes' => $lignes];
            }
            $situation = $this->frais->getSituationDeuxTranches($contexte['eleve_id'], $contexte['annee_scolaire_id']);
            $resteDu = array_sum(array_column($situation, 'reste'));

            if ($montantVerse > $resteDu + 0.001) {
                throw new VersementSuperieurAuDuException($montantVerse, $resteDu);
            }

            $reference = (string) Str::uuid();
            $restant = $montantVerse;
            $lignes = [];

            foreach (['inscription', 'tranche_1', 'tranche_2'] as $rubrique) {
                $affecte = min($restant, $situation[$rubrique]['reste']);
                if ($affecte <= 0) {
                    continue;
                }

                $lignes[] = $this->paiements->enregistrerPaiement(
                    $contexte['eleve_id'],
                    $affecte,
                    $mode,
                    $referenceMobileMoney,
                    [
                        'inscription_id' => $inscriptionId,
                        'versement_reference' => $reference,
                        'rubrique' => $rubrique,
                        'libelle_rubrique' => match ($rubrique) {
                            'inscription' => "Frais d'inscription",
                            'tranche_1' => 'Tranche 1 scolarité',
                            'tranche_2' => 'Tranche 2 scolarité',
                        },
                    ],
                );
                $restant = round($restant - $affecte, 2);
            }

            return ['versement_reference' => $reference, 'montant' => $montantVerse, 'lignes' => $lignes];
        });
    }

    public function payerFraisDivers(int $fraisDiversEleveId, float $montantVerse, string $mode, ?string $referenceMobileMoney = null): object
    {
        if ($montantVerse <= 0) {
            throw new MontantPaiementInvalideException($montantVerse);
        }

        return DB::transaction(function () use ($fraisDiversEleveId, $montantVerse, $mode, $referenceMobileMoney): object {
            $frais = FraisDiversEleve::query()->with('poste')->lockForUpdate()->findOrFail($fraisDiversEleveId);
            if ($frais->statut !== 'actif') {
                throw new \DomainException('Ce frais divers est annulé et ne peut plus être payé.');
            }
            $dejaPaye = (float) Paiement::query()
                ->where('frais_divers_eleve_id', $frais->id)
                ->where('statut', 'valide')
                ->sum('montant');
            $resteDu = round(max(0, (float) $frais->montant - $dejaPaye), 2);
            if ($montantVerse > $resteDu + 0.001) {
                throw new VersementSuperieurAuDuException($montantVerse, $resteDu);
            }

            return $this->paiements->enregistrerPaiement(
                (int) $frais->eleve_id,
                $montantVerse,
                $mode,
                $referenceMobileMoney,
                [
                    'versement_reference' => (string) Str::uuid(),
                    'rubrique' => 'divers:'.$frais->id,
                    'libelle_rubrique' => $frais->poste->nom,
                    'frais_divers_eleve_id' => $frais->id,
                ],
            );
        });
    }
}
