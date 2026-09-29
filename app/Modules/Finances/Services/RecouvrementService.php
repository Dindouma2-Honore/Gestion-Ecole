<?php

declare(strict_types=1);

namespace App\Modules\Finances\Services;

use App\Modules\Finances\Contracts\ConfigurationFraisClasseServiceContract;
use App\Modules\Finances\Contracts\FraisScolaireServiceContract;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Contracts\RecouvrementServiceContract;
use App\Modules\Finances\Models\EcheancierNegocie;
use App\Modules\Finances\Models\PromessePaiement;
use App\Modules\Finances\Models\RelancePaiement;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\NotificationServiceContract;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class RecouvrementService implements RecouvrementServiceContract
{
    public function __construct(
        private readonly PaiementServiceContract $paiement,
        private readonly FraisScolaireServiceContract $fraisScolaire,
        private readonly NotificationServiceContract $notification,
        private readonly AnneeScolaireServiceContract $anneeScolaire,
        private readonly EleveServiceInterface $eleves,
        private readonly ConfigurationFraisClasseServiceContract $configurationsClasse,
    ) {}

    public function traiterRelancesImpayes(): void
    {
        foreach ($this->getListeDebiteurs() as $debiteur) {
            $derniereRelance = RelancePaiement::where('eleve_id', $debiteur['eleve_id'])
                ->orderByDesc('date_relance')
                ->orderByDesc('id')
                ->first();

            if ($derniereRelance && $derniereRelance->date_relance->isAfter(now()->subDays(15))) {
                continue; // pas de relance trop fréquente
            }

            $niveauRelance = ($derniereRelance?->niveau_relance ?? 0) + 1;
            $canal = $niveauRelance >= 3 ? 'lettre' : 'whatsapp';

            RelancePaiement::create([
                'eleve_id' => $debiteur['eleve_id'],
                'niveau_relance' => $niveauRelance,
                'canal' => $canal,
                'date_relance' => now(),
                'reste_a_payer_constate' => $debiteur['reste_a_payer'],
            ]);

            $this->notification->envoyer(
                canal: $canal === 'lettre' ? 'email' : $canal,
                code: 'relance_impaye_niveau_'.min($niveauRelance, 3),
                eleveId: $debiteur['eleve_id'],
                donnees: ['montant' => $debiteur['reste_a_payer']],
            );
        }
    }

    public function getListeDebiteurs(?int $niveauId = null): Collection
    {
        $anneeId = $this->anneeScolaire->getAnneeCouranteId();

        return collect($this->eleves->getElevesActifsIds($niveauId))
            ->map(function (int $eleveId) use ($anneeId): array {
                $eleve = $this->eleves->getEleve($eleveId);
                $reste = $this->paiement->getResteAPayer($eleveId, $anneeId);
                $retard = $reste;
                if (! empty($eleve['classe_id']) && $this->configurationsClasse->obtenir((int) $eleve['classe_id'], $anneeId) !== null) {
                    $situation = $this->configurationsClasse->situation($eleveId, (int) $eleve['classe_id'], $anneeId);
                    $reste = $situation['reste_a_payer'];
                    $retard = $situation['montant_en_retard'];
                }

                return [
                    'eleve_id' => $eleveId,
                    'nom' => $eleve['nom'],
                    'prenom' => $eleve['prenom'],
                    'reste_a_payer' => $reste,
                    'montant_en_retard' => $retard,
                ];
            })
            ->filter(fn (array $debiteur): bool => $debiteur['montant_en_retard'] > 0)
            ->values();
    }

    public function proposerEcheancierNegocie(int $eleveId, float $montantTotal, int $nombreTranches, DateTimeInterface $datePremiereTranche): object
    {
        return EcheancierNegocie::create([
            'eleve_id' => $eleveId,
            'montant_total' => $montantTotal,
            'nombre_tranches' => $nombreTranches,
            'date_premiere_tranche' => $datePremiereTranche,
            'statut' => 'propose',
            'approuve_par' => Auth::id(),
        ]);
    }

    public function enregistrerPromesse(int $eleveId, DateTimeInterface $datePromesse, float $montant): object
    {
        return PromessePaiement::create([
            'eleve_id' => $eleveId,
            'date_promesse' => $datePromesse,
            'montant_promis' => $montant,
        ]);
    }

    public function getTauxRecouvrement(int $anneeScolaireId): float
    {
        // Somme des montants dus vs somme des montants payés, sur tous les
        // élèves actifs, en réutilisant FraisScolaireServiceContract::
        // getMontantDu() et PaiementServiceContract::getTotalPaye() en
        // boucle. À revoir avec une requête agrégée dédiée si la
        // performance devient un problème avec un grand nombre d'élèves —
        // à surveiller une fois en production, pas à optimiser
        // prématurément.
        $totalDu = 0.0;
        $totalPaye = 0.0;

        foreach ($this->eleves->getElevesActifsIds() as $eleveId) {
            $totalDu += $this->fraisScolaire->getMontantDu($eleveId, $anneeScolaireId);
            $totalPaye += $this->paiement->getTotalPaye($eleveId, $anneeScolaireId);
        }

        if ($totalDu <= 0.0) {
            return 0.0;
        }

        return round(min($totalPaye, $totalDu) / $totalDu * 100, 2);
    }
}
