<?php

declare(strict_types=1);

namespace App\Modules\Finances\Services;

use App\Modules\Finances\Contracts\FraisScolaireServiceContract;
use App\Modules\Finances\Exceptions\EleveIntrouvableException;
use App\Modules\Finances\Exceptions\RemiseSansMotifException;
use App\Modules\Finances\Models\EcheancierPaiement;
use App\Modules\Finances\Models\FraisDiversEleve;
use App\Modules\Finances\Models\GrilleFrais;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Finances\Models\RemiseExoneration;
use App\Modules\Finances\Models\TypeFraisRecurrent;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class FraisScolaireService implements FraisScolaireServiceContract
{
    public function __construct(
        private EleveServiceInterface $eleves,
        private AuditServiceContract $audit,
        private AnneeScolaireServiceContract $anneeScolaire,
    ) {}

    public function getMontantDu(int $eleveId, int $anneeScolaireId): float
    {
        return round(array_sum($this->getMontantDuParGroupe($eleveId, $anneeScolaireId)), 2);
    }

    public function getMontantDuParGroupe(int $eleveId, int $anneeScolaireId): array
    {
        $niveauId = $this->niveauDeLEleve($eleveId);
        $lignes = collect();

        TypeFraisRecurrent::query()
            ->with('groupe')
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('actif', true)
            ->where(fn ($query) => $query->whereNull('niveau_id')->orWhere('niveau_id', $niveauId))
            ->get()
            ->each(function (TypeFraisRecurrent $type) use ($lignes): void {
                $lignes->push([
                    'groupe' => $type->groupe->code,
                    'cibles' => [Str::slug($type->nom)],
                    'montant' => (float) $type->montant,
                ]);
            });

        FraisDiversEleve::query()
            ->with('poste.groupe')
            ->where('eleve_id', $eleveId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('statut', '!=', 'annule')
            ->whereHas('poste', fn ($query) => $query->where('actif', true))
            ->get()
            ->each(function (FraisDiversEleve $frais) use ($lignes): void {
                $lignes->push([
                    'groupe' => $frais->poste->groupe->code,
                    'cibles' => [Str::slug($frais->poste->nom), $frais->poste->categorie],
                    'montant' => (float) $frais->montant,
                ]);
            });

        $remises = RemiseExoneration::query()
            ->where('eleve_id', $eleveId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->get();

        foreach ($remises as $remise) {
            $cible = Str::slug($remise->type_frais);
            $indices = $lignes->keys()->filter(fn (int $index): bool => in_array($cible, $lignes[$index]['cibles'], true));
            $brutCible = (float) $indices->sum(fn (int $index): float => $lignes[$index]['montant']);
            if ($brutCible <= 0) {
                continue;
            }

            $reduction = match ($remise->type) {
                'exoneration_totale' => $brutCible,
                'remise_pourcentage' => $brutCible * (float) $remise->valeur / 100,
                'remise_montant' => min($brutCible, (float) $remise->valeur),
            };

            // Répartit proportionnellement une remise qui cible plusieurs
            // lignes, tout en conservant exactement le total net attendu.
            foreach ($indices as $index) {
                $ligne = $lignes->get($index);
                $part = $ligne['montant'] / $brutCible;
                $ligne['montant'] = max(0.0, $ligne['montant'] - ($reduction * $part));
                $lignes->put($index, $ligne);
            }
        }

        return [
            'scolarite' => round((float) $lignes->where('groupe', 'scolarite')->sum('montant'), 2),
            'autres' => round((float) $lignes->where('groupe', 'autres')->sum('montant'), 2),
        ];
    }

    public function getTotalImpayes(int $anneeScolaireId): float
    {
        $total = 0.0;

        foreach ($this->eleves->getElevesPourSituationFinanciere() as $eleve) {
            $du = $this->getMontantDu($eleve['id'], $anneeScolaireId);
            $paye = (float) Paiement::query()->where('eleve_id', $eleve['id'])->where('annee_scolaire_id', $anneeScolaireId)->where('statut', 'valide')->sum('montant');
            $total += max(0.0, $du - $paye);
        }

        return round($total, 2);
    }

    public function getEcheancier(int $eleveId, int $anneeScolaireId): Collection
    {
        $niveauId = $this->niveauDeLEleve($eleveId);

        $grilleIds = GrilleFrais::query()
            ->whereHas('typeRecurrent', fn ($query) => $query
                ->where('annee_scolaire_id', $anneeScolaireId)
                ->where(fn ($niveaux) => $niveaux->whereNull('niveau_id')->orWhere('niveau_id', $niveauId))
                ->where('actif', true))
            ->pluck('id');

        return EcheancierPaiement::query()
            ->whereIn('grille_frais_id', $grilleIds)
            ->orderBy('ordre')
            ->get();
    }

    public function getSituationParFrais(int $eleveId, int $anneeScolaireId): array
    {
        $situation = collect($this->getSituationDeuxTranches($eleveId, $anneeScolaireId))->mapWithKeys(fn (array $ligne, string $code): array => [$code => [
            'libelle' => match ($code) {
                'inscription' => 'Frais d’inscription', 'tranche_1' => 'Tranche 1', 'tranche_2' => 'Tranche 2', default => Str::headline($code)
            },
            'attendu' => (float) $ligne['du'], 'recu' => (float) $ligne['paye'], 'restant' => (float) $ligne['reste'],
        ]]);

        $niveauId = $this->niveauDeLEleve($eleveId);
        TypeFraisRecurrent::query()->where('annee_scolaire_id', $anneeScolaireId)->where('actif', true)->where('nature', 'autre')
            ->where(fn ($query) => $query->whereNull('niveau_id')->orWhere('niveau_id', $niveauId))->get()
            ->each(function (TypeFraisRecurrent $type) use ($situation, $eleveId, $anneeScolaireId): void {
                $code = Str::slug($type->nom, '_');
                $recu = (float) Paiement::query()->where('eleve_id', $eleveId)->where('annee_scolaire_id', $anneeScolaireId)->where('statut', 'valide')->where('rubrique', $code)->sum('montant');
                $attendu = (float) $type->montant;
                $situation->put($code, ['libelle' => $type->nom, 'attendu' => $attendu, 'recu' => min($attendu, $recu), 'restant' => max(0, $attendu - $recu)]);
            });

        FraisDiversEleve::query()->with('poste')->where('eleve_id', $eleveId)->where('annee_scolaire_id', $anneeScolaireId)->where('statut', '!=', 'annule')->get()->each(function (FraisDiversEleve $frais) use ($situation): void {
            $code = $frais->poste->categorie;
            $recu = (float) Paiement::query()->where('eleve_id', $frais->eleve_id)->where('annee_scolaire_id', $frais->annee_scolaire_id)->where('statut', 'valide')->where('rubrique', $code)->sum('montant');
            $attendu = (float) $frais->montant;
            $situation->put($code, ['libelle' => $frais->poste->nom, 'attendu' => $attendu, 'recu' => min($attendu, $recu), 'restant' => max(0, $attendu - $recu)]);
        });

        return $situation->all();
    }

    public function getSituationDeuxTranches(int $eleveId, int $anneeScolaireId): array
    {
        $niveauId = $this->niveauDeLEleve($eleveId);
        $montantScolarite = $this->getMontantDuParGroupe($eleveId, $anneeScolaireId)['scolarite'];
        $types = TypeFraisRecurrent::query()
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('actif', true)
            ->where(fn ($query) => $query->whereNull('niveau_id')->orWhere('niveau_id', $niveauId))
            ->get();

        $inscription = round((float) $types->where('nature', 'inscription')->sum('montant'), 2);
        $soldeScolarite = max(0.0, round($montantScolarite - $inscription, 2));
        $ratio = (float) ($types->firstWhere('nature', 'scolarite')?->ratio_tranche_1 ?? 50);
        $ratio = min(100.0, max(0.0, $ratio));
        $tranche1 = round($soldeScolarite * $ratio / 100, 2);
        $dus = [
            'inscription' => $inscription,
            'tranche_1' => $tranche1,
            'tranche_2' => round($soldeScolarite - $tranche1, 2),
        ];

        $payes = Paiement::query()
            ->where('eleve_id', $eleveId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('statut', 'valide')
            ->whereIn('rubrique', array_keys($dus))
            ->selectRaw('rubrique, SUM(montant) as total')
            ->groupBy('rubrique')
            ->pluck('total', 'rubrique');

        $situation = [];
        foreach ($dus as $rubrique => $du) {
            $paye = min($du, round((float) ($payes[$rubrique] ?? 0), 2));
            $situation[$rubrique] = ['du' => $du, 'paye' => $paye, 'reste' => round($du - $paye, 2), 'statut' => 'en_attente'];
        }

        $situation['inscription']['statut'] = $situation['inscription']['reste'] <= 0 ? 'soldee' : 'en_cours';
        $situation['tranche_1']['statut'] = $situation['tranche_1']['reste'] <= 0
            ? 'soldee'
            : ($situation['inscription']['reste'] <= 0 ? 'en_cours' : 'en_attente');
        $situation['tranche_2']['statut'] = $situation['tranche_2']['reste'] <= 0
            ? 'soldee'
            : ($situation['tranche_1']['reste'] <= 0 ? 'en_cours' : 'en_attente');

        return $situation;
    }

    public function accorderRemise(int $eleveId, string $typeFrais, string $type, float $valeur, string $motif): object
    {
        if (trim($motif) === '') {
            throw new RemiseSansMotifException;
        }

        if (! $this->eleves->existe($eleveId)) {
            throw new EleveIntrouvableException($eleveId);
        }

        $approbateurId = Auth::id();

        if ($approbateurId === null) {
            throw new AccessDeniedHttpException('Une authentification est requise pour accorder une remise.');
        }

        $remise = RemiseExoneration::create([
            'eleve_id' => $eleveId,
            'type_frais' => $typeFrais,
            'type' => $type,
            'valeur' => $valeur,
            'motif' => $motif,
            'annee_scolaire_id' => $this->anneeScolaire->getAnneeCouranteId(),
            'approuve_par' => $approbateurId,
        ]);

        $this->audit->enregistrerAvecMotif(
            $remise,
            "Remise/exonération accordée à l'élève #{$eleveId}",
            $motif,
        );

        return $remise;
    }

    public function getGrilleFrais(int $niveauId, int $anneeScolaireId): Collection
    {
        return GrilleFrais::query()
            ->whereHas('typeRecurrent', fn ($query) => $query
                ->where('annee_scolaire_id', $anneeScolaireId)
                ->where(fn ($niveaux) => $niveaux->whereNull('niveau_id')->orWhere('niveau_id', $niveauId))
                ->where('actif', true))
            ->get();
    }

    private function niveauDeLEleve(int $eleveId): int
    {
        if (! $this->eleves->existe($eleveId)) {
            throw new EleveIntrouvableException($eleveId);
        }

        return $this->eleves->getNiveauId($eleveId);
    }
}
