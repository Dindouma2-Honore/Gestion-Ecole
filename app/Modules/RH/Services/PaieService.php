<?php

declare(strict_types=1);

namespace App\Modules\RH\Services;

use App\Modules\Communication\Services\WhatsAppDispatcher;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\RH\Contracts\AbsenceServiceContract;
use App\Modules\RH\Contracts\AvanceSalaireServiceContract;
use App\Modules\RH\Contracts\ContratServiceInterface;
use App\Modules\RH\Contracts\DisciplinePersonnelServiceContract;
use App\Modules\RH\Contracts\HeuresTravailleesServiceContract;
use App\Modules\RH\Contracts\PaieServiceContract;
use App\Modules\RH\Contracts\PointageServiceContract;
use App\Modules\RH\Contracts\PrimeServiceContract;
use App\Modules\RH\Exceptions\BulletinDejaExistantException;
use App\Modules\RH\Exceptions\BulletinDejaValideException;
use App\Modules\RH\Exceptions\ContratInexistantException;
use App\Modules\RH\Models\AvanceSalaire;
use App\Modules\RH\Models\BulletinPaie;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\EtatVirement;
use App\Modules\RH\Models\PalierAnciennete;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PaieService implements PaieServiceContract
{
    public function __construct(
        private readonly ContratServiceInterface $contratService,
        private readonly DisciplinePersonnelServiceContract $disciplineService,
        private readonly PrimeServiceContract $primeService,
        private readonly PointageServiceContract $pointageService,
        private readonly AbsenceServiceContract $absenceService,
        private readonly AvanceSalaireServiceContract $avanceService,
        private readonly HeuresTravailleesServiceContract $heuresTravaillees,
        private readonly AnneeScolaireServiceContract $anneeScolaire,
        private readonly ParametrageServiceContract $parametrage,
        private readonly CaisseServiceContract $caisse,
        private readonly AuditServiceContract $audit,
    ) {}

    public function demanderAvance(int $employeId, float $montant, string $motif, bool $derogationPlafond = false, ?string $motifDerogation = null): object
    {
        $user = Auth::user() ?? throw new AccessDeniedHttpException('Une authentification est requise.');
        $contrat = $this->contratService->getContratActif($employeId) ?? throw new ContratInexistantException($employeId);
        $plafond = round((float) $contrat->salaire_base * 0.30, 2);
        if ($montant <= 0 || trim($motif) === '') {
            throw new \DomainException('Le montant et le motif de la demande sont obligatoires.');
        }
        if ($montant > $plafond && (! $derogationPlafond || ! $user->hasRole('Fondateur') || trim((string) $motifDerogation) === '')) {
            throw new \DomainException("L’avance ne peut pas dépasser 30 % du salaire de base ({$plafond} FCFA) sans dérogation motivée du Fondateur.");
        }
        $avance = AvanceSalaire::create([
            'employe_id' => $employeId, 'montant' => $montant, 'date_demande' => today(),
            'motif' => trim($motif), 'demande_par' => $user->id, 'statut' => 'demande',
            'derogation_plafond' => $montant > $plafond, 'motif_derogation' => $montant > $plafond ? trim((string) $motifDerogation) : null,
        ]);
        $this->audit->enregistrerAvecMotif($avance, 'Demande d’avance sur salaire', trim($motif));

        return $avance;
    }

    public function validerAvance(int $avanceId, string $motifValidation): object
    {
        $user = Auth::user();
        if (! $user?->hasRole('Fondateur') || trim($motifValidation) === '') {
            throw new AccessDeniedHttpException('La validation motivée du Fondateur est requise.');
        }

        return DB::transaction(function () use ($avanceId, $motifValidation, $user): AvanceSalaire {
            $avance = AvanceSalaire::query()->lockForUpdate()->findOrFail($avanceId);
            if ($avance->statut !== 'demande') {
                throw new \DomainException('Seule une demande en attente peut être validée.');
            }
            $this->caisse->enregistrerMouvement('decaissement', (float) $avance->montant, $avance, null, 'Avances de salaire', 'RH', 'Avances');
            $avance->update(['statut' => 'approuvee', 'valide_par' => $user->id, 'validee_le' => now(), 'decaissee_le' => now()]);
            $this->audit->enregistrerAvecMotif($avance, 'Validation et décaissement d’une avance sur salaire', trim($motifValidation));

            return $avance->refresh();
        });
    }

    public function calculerBulletin(int $employeId, int $mois, int $annee, ?\DateTimeInterface $periodeDebut = null, ?\DateTimeInterface $periodeFin = null): BulletinPaie
    {
        $contrat = $this->contratService->getContratActif($employeId);
        if (! $contrat) {
            throw new ContratInexistantException($employeId);
        }
        if (BulletinPaie::query()->where('employe_id', $employeId)->where('mois', $mois)->where('annee', $annee)->exists()) {
            throw new BulletinDejaExistantException($employeId, $mois, $annee);
        }

        $this->actualiserAncienneteEtGrille($employeId, $contrat, $mois, $annee);
        $contrat->refresh();

        $periodeDebut ??= Carbon::create($annee, $mois, 1);
        $periodeFin ??= Carbon::create($annee, $mois, 1)->endOfMonth();

        if ($periodeFin < $periodeDebut) {
            throw new \InvalidArgumentException('La fin de période doit être postérieure ou égale au début de période.');
        }

        return DB::transaction(function () use ($employeId, $mois, $annee, $periodeDebut, $periodeFin, $contrat): BulletinPaie {
            $avecFixe = in_array($contrat->categorie_paie, ['fixe', 'mixte'], true);
            $avecHoraire = in_array($contrat->categorie_paie, ['horaire', 'mixte'], true);
            $heures = $avecHoraire
                ? $this->heuresTravaillees->getNombreHeuresTravailleesPourPeriode($employeId, $periodeDebut, $periodeFin)
                : 0.0;
            $composanteFixe = $avecFixe ? $this->proratiserMontantMensuel((float) $contrat->salaire_base, $periodeDebut, $periodeFin) : 0.0;
            $composanteHoraire = $avecHoraire ? round((float) $contrat->taux_horaire * $heures, 2) : 0.0;
            $fonctionsRemunerees = $contrat->fonctionsRemunerees()
                ->with('fonction')
                ->whereDate('date_effet', '<=', $periodeFin)
                ->get();
            $fonctionFixeMensuelle = (float) $fonctionsRemunerees
                ->where('mode_remuneration', 'salaire_base')
                ->sum('montant');
            $fonctionFixe = $this->proratiserMontantMensuel($fonctionFixeMensuelle, $periodeDebut, $periodeFin);
            $fonctionHoraire = $avecHoraire
                ? round((float) $fonctionsRemunerees
                    ->where('mode_remuneration', 'tarif_horaire')
                    ->sum('montant') * $heures, 2)
                : 0.0;
            $salaireBase = $composanteFixe + $composanteHoraire + $fonctionFixe + $fonctionHoraire;
            $primes = $this->primeService->getPrimesPourPeriode(
                $employeId,
                Carbon::instance($periodeDebut),
                Carbon::instance($periodeFin),
            );
            $totalPrimes = (float) $primes->sum('montant');
            [$retenueAbsences, $retenueSuspension] = $avecFixe ? $this->retenuesFixes($employeId, $periodeDebut, $periodeFin, $composanteFixe) : [0.0, 0.0];
            $taux = (float) $this->parametrage->getParametre('tauxCotisationCnpsSalarie');
            // Hypothèse confirmée provisoirement : les cotisations s'appliquent aussi à la branche horaire.
            $cotisations = round($salaireBase * $taux, 2);
            $avance = AvanceSalaire::query()
                ->where('employe_id', $employeId)
                ->where('statut', 'approuvee')
                ->whereDate(DB::raw('COALESCE(decaissee_le, date_demande)'), '<=', $periodeFin)
                ->orderByRaw('COALESCE(decaissee_le, date_demande)')
                ->first();
            $netAvantAvance = max(0, $salaireBase + $totalPrimes - $retenueAbsences - $retenueSuspension - $cotisations);
            $avanceADeduire = min($avance?->resteADeduire() ?? 0.0, $netAvantAvance);
            $anneeScolaireId = $this->anneeScolaire->getAnneeCourante()?->id;
            if (! $anneeScolaireId) {
                throw new \RuntimeException('Aucune année scolaire active.');
            }

            $bulletin = BulletinPaie::create([
                'employe_id' => $employeId, 'contrat_id' => $contrat->id,
                'mois' => $mois, 'annee' => $annee, 'annee_scolaire_id' => $anneeScolaireId,
                'periode_debut' => $periodeDebut, 'periode_fin' => $periodeFin,
                'salaire_base' => $salaireBase, 'total_primes' => $totalPrimes,
                'total_heures_supplementaires' => 0,
                'total_retenues' => $retenueAbsences + $retenueSuspension,
                'total_cotisations' => $cotisations, 'avances_deduites' => $avanceADeduire,
                'net_a_payer' => $netAvantAvance - $avanceADeduire,
                'statut' => 'calcule',
            ]);
            if ($avecFixe) {
                $bulletin->lignes()->create(['type' => 'gain', 'libelle' => 'Salaire de base', 'montant' => $composanteFixe, 'date_reference' => $periodeDebut]);
            }
            if ($avecHoraire) {
                $bulletin->lignes()->create(['type' => 'gain', 'libelle' => sprintf('Rémunération horaire : %.2f h × %s FCFA', $heures, number_format((float) $contrat->taux_horaire, 0, ',', ' ')), 'montant' => $composanteHoraire, 'date_reference' => $periodeDebut]);
            }
            if ($fonctionFixe > 0) {
                $bulletin->lignes()->create(['type' => 'gain', 'libelle' => 'Rémunération fonction administrative', 'montant' => $fonctionFixe, 'date_reference' => $fonctionsRemunerees->first()?->date_effet]);
            }
            if ($fonctionHoraire > 0) {
                $bulletin->lignes()->create(['type' => 'gain', 'libelle' => sprintf('Rémunération horaire fonction : %.2f h', $heures), 'montant' => $fonctionHoraire, 'date_reference' => $fonctionsRemunerees->first()?->date_effet]);
            }
            $this->creerLignes($bulletin, $primes, $retenueAbsences, $retenueSuspension, $cotisations, $avanceADeduire, $avance);
            $primeIds = $primes->pluck('prime_id')->filter()->values()->all();
            if ($primeIds !== []) {
                $this->primeService->marquerPrimesIntegrees($primeIds, (int) $bulletin->id);
            }
            if ($avance && $avanceADeduire > 0) {
                $this->avanceService->enregistrerRecouvrement($avance->id, $avanceADeduire);
            }

            return $bulletin;
        });
    }

    private function actualiserAncienneteEtGrille(int $employeId, object $contrat, int $mois, int $annee): void
    {
        $employe = Employe::query()->findOrFail($employeId);
        $datePaie = Carbon::create($annee, $mois, 1)->endOfMonth();
        $anciennete = $employe->date_embauche?->diffInMonths($datePaie) ?? 0;
        $categorie = PalierAnciennete::query()
            ->where('actif', true)
            ->where('anciennete_min_mois', '<=', $anciennete)
            ->where(fn ($q) => $q->whereNull('anciennete_max_mois')->orWhere('anciennete_max_mois', '>=', $anciennete))
            ->orderByDesc('anciennete_min_mois')
            ->first();

        if (! $categorie) {
            return;
        }

        $employe->update(['palier_anciennete_id' => $categorie->id]);
        $ancienneLigne = $contrat->grille_salariale_id ? DB::table('grilles_salariales')->find($contrat->grille_salariale_id) : null;
        if (! $ancienneLigne) {
            return;
        }

        $nouvelleLigne = DB::table('grilles_salariales')
            ->where('palier_anciennete_id', $categorie->id)
            ->where('base_calcul', $ancienneLigne->base_calcul)
            ->where('matiere_id', $ancienneLigne->matiere_id)
            ->where('poste_administratif_id', $ancienneLigne->poste_administratif_id)
            ->where('tache', $ancienneLigne->tache)
            ->where('actif', true)
            ->where(fn ($q) => $q->whereNull('date_effet')->orWhereDate('date_effet', '<=', $datePaie))
            ->orderByDesc('date_effet')
            ->first();

        if ($nouvelleLigne) {
            $contrat->update([
                'grille_salariale_id' => $nouvelleLigne->id,
                'salaire_base' => $nouvelleLigne->salaire_base,
                'taux_horaire' => $nouvelleLigne->taux_horaire,
            ]);
        }
    }

    private function retenuesFixes(int $employeId, \DateTimeInterface $debut, \DateTimeInterface $fin, float $base): array
    {
        $joursAbsence = $this->absenceService->getJoursAbsenceNonJustifieePourPeriode(
            $employeId,
            Carbon::instance($debut),
            Carbon::instance($fin),
        );

        return [$this->retenue($base, $joursAbsence), 0.0];
    }

    private function proratiserMontantMensuel(float $montant, \DateTimeInterface $debut, \DateTimeInterface $fin): float
    {
        $debut = Carbon::instance($debut)->startOfDay();
        $fin = Carbon::instance($fin)->startOfDay();
        $total = 0.0;
        $jour = $debut->copy()->startOfMonth();

        while ($jour->lte($fin)) {
            $debutMois = $jour->copy()->startOfMonth();
            $finMois = $jour->copy()->endOfMonth();
            $partDebut = $debut->greaterThan($debutMois) ? $debut : $debutMois;
            $partFin = $fin->lessThan($finMois) ? $fin : $finMois;
            if ($partDebut->lte($partFin)) {
                $total += ($montant / $jour->daysInMonth) * ($partDebut->diffInDays($partFin) + 1);
            }
            $jour->addMonth()->startOfMonth();
        }

        return round($total, 2);
    }

    private function joursAbsenceNonJustifiee(int $employeId, int $mois, int $annee): int
    {
        return $this->absenceService->getJoursAbsenceNonJustifiee($employeId, $mois, $annee);
    }

    private function retenue(float $base, int $jours): float
    {
        $ouvrables = (int) $this->parametrage->getParametre('joursOuvrablesPaie');

        return $ouvrables > 0 ? round(($base / $ouvrables) * $jours, 2) : 0.0;
    }

    private function joursSuspension(int $employeId, int $mois, int $annee): int
    {
        return $this->absenceService->getJoursSuspension($employeId, $mois, $annee);
    }

    private function creerLignes(BulletinPaie $bulletin, Collection $primes, float $absences, float $suspensions, float $cotisations, float $avance, ?AvanceSalaire $avanceModel): void
    {
        foreach ($primes as $prime) {
            $bulletin->lignes()->create(['type' => 'prime', 'libelle' => $prime->libelle, 'montant' => $prime->montant, 'date_reference' => $prime->date_reference ?? null]);
        }
        foreach ([['retenue', 'Absences non justifiées', $absences], ['retenue', 'Suspension disciplinaire', $suspensions], ['cotisation', 'CNPS', $cotisations], ['avance', 'Remboursement avance sur salaire', $avance]] as [$type, $libelle, $montant]) {
            if ($montant > 0) {
                $bulletin->lignes()->create(compact('type', 'libelle') + [
                    'montant' => -$montant,
                    'date_reference' => $type === 'avance'
                        ? ($avanceModel?->decaissee_le?->toDateString() ?? $avanceModel?->date_demande?->toDateString())
                        : $bulletin->periode_debut,
                ]);
            }
        }
    }

    public function validerBulletin(int $bulletinId, int $validateurId): void
    {
        $bulletin = DB::transaction(function () use ($bulletinId): BulletinPaie {
            $bulletin = BulletinPaie::query()->lockForUpdate()->findOrFail($bulletinId);
            if (in_array($bulletin->statut, ['valide', 'paye'], true)) {
                throw new BulletinDejaValideException($bulletinId);
            }
            if (! in_array($bulletin->statut, ['brouillon', 'calcule'], true)) {
                throw new \DomainException("Un bulletin au statut « {$bulletin->statut} » ne peut pas être validé.");
            }
            $bulletin->update(['statut' => 'valide']);

            return $bulletin;
        });

        app(WhatsAppDispatcher::class)->dispatchBulletin($bulletin->refresh());
    }

    /**
     * RH constate le montant dû (bulletin validé) ; le décaissement effectif appartient à Finance.
     * Le bulletin ne passe PAYÉ qu'après l'enregistrement du mouvement par la frontière Finance,
     * dans la même transaction : si Finance échoue, le bulletin reste VALIDÉ.
     */
    public function marquerPaye(int $bulletinId, \DateTimeInterface $datePaiement): void
    {
        DB::transaction(function () use ($bulletinId, $datePaiement): void {
            $bulletin = BulletinPaie::query()->lockForUpdate()->findOrFail($bulletinId);
            if ($bulletin->statut !== 'valide') {
                throw new BulletinDejaValideException($bulletinId);
            }

            $this->caisse->enregistrerMouvement('decaissement', (float) $bulletin->net_a_payer, $bulletin, null, 'Salaires', 'RH', 'Salaires');
            BulletinPaie::query()->whereKey($bulletin->id)->update(['statut' => 'paye', 'date_paiement' => $datePaiement]);
            $this->audit->enregistrerAvecMotif($bulletin, 'Paiement du bulletin de paie', "Bulletin marqué payé à la date {$datePaiement->format('Y-m-d')}");
        });
    }

    public function getMasseSalariale(int $mois, int $annee): float
    {
        return (float) BulletinPaie::query()->where('mois', $mois)->where('annee', $annee)->whereIn('statut', ['valide', 'paye'])->sum('net_a_payer');
    }

    public function getSalairesDus(int $mois, int $annee): float
    {
        return round((float) BulletinPaie::query()->where('mois', $mois)->where('annee', $annee)->whereIn('statut', ['calcule', 'valide'])->sum('net_a_payer'), 2);
    }

    public function genererEtatVirement(int $mois, int $annee): object
    {
        return EtatVirement::query()->firstOrCreate(
            ['mois' => $mois, 'annee' => $annee],
            ['date_generation' => now(), 'genere_par' => Auth::id() ?? throw new \RuntimeException('Utilisateur requis.')],
        );
    }
}
