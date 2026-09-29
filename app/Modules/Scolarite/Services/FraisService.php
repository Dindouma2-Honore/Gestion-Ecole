<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Services;

use App\Modules\Scolarite\Contracts\FraisServiceContract;
use App\Modules\Scolarite\Exceptions\CategorieFraisEnUsageException;
use App\Modules\Scolarite\Exceptions\TarifClasseNonDefiniException;
use App\Modules\Scolarite\Models\CategorieFrais;
use App\Modules\Scolarite\Models\Frais;
use App\Modules\Scolarite\Models\FraisEleve;
use App\Modules\Scolarite\Models\GrilleTarifaire;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FraisService implements FraisServiceContract
{
    public function creerCategorie(string $nom): CategorieFrais
    {
        return CategorieFrais::create(['nom' => $nom]);
    }

    public function modifierCategorie(int $id, string $nom): void
    {
        CategorieFrais::findOrFail($id)->update(['nom' => $nom]);
    }

    public function supprimerCategorie(int $id): void
    {
        if (Frais::where('categorie_frais_id', $id)->exists()) {
            throw new CategorieFraisEnUsageException($id);
        }

        CategorieFrais::findOrFail($id)->delete();
    }

    public function listerCategories(): Collection
    {
        return CategorieFrais::orderBy('nom')->get();
    }

    public function creerFrais(
        string $nom,
        float $montant,
        int $categorieId,
        bool $utiliseGrilleTarifaire = false,
        int $ordreRepartition = 999,
    ): Frais {
        return Frais::create([
            'nom' => $nom,
            'montant' => $utiliseGrilleTarifaire ? 0 : $montant,
            'categorie_frais_id' => $categorieId,
            'utilise_grille_tarifaire' => $utiliseGrilleTarifaire,
            'ordre_repartition' => $utiliseGrilleTarifaire ? $ordreRepartition : 999,
        ]);
    }

    public function modifierFrais(int $id, array $donnees): void
    {
        // Modifier ordre_repartition sur un frais existant ne doit jamais
        // rejouer la répartition des paiements déjà effectués — seuls les
        // versements futurs suivent le nouvel ordre (Module 5 §5). Comme la
        // répartition est calculée à chaque enregistrerPaiement() à partir
        // de l'état courant de `frais`, ceci est déjà garanti sans code
        // supplémentaire : les paiement_repartitions passées ne sont jamais
        // retouchées.
        Frais::findOrFail($id)->update(array_intersect_key($donnees, array_flip([
            'nom', 'montant', 'categorie_frais_id', 'utilise_grille_tarifaire', 'ordre_repartition',
        ])));
    }

    public function supprimerFrais(int $id): void
    {
        Frais::findOrFail($id)->delete();
    }

    public function getFraisDiversDisponibles(): Collection
    {
        return Frais::where('utilise_grille_tarifaire', false)->orderBy('nom')->get();
    }

    public function definirTarifClasse(int $fraisId, int $classeId, int $anneeScolaireId, float $montant): void
    {
        GrilleTarifaire::updateOrCreate(
            ['frais_id' => $fraisId, 'classe_id' => $classeId, 'annee_scolaire_id' => $anneeScolaireId],
            ['montant' => $montant],
        );
    }

    public function getTarifClasse(int $fraisId, int $classeId, int $anneeScolaireId): float
    {
        $tarif = GrilleTarifaire::where('frais_id', $fraisId)
            ->where('classe_id', $classeId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->first();

        if (! $tarif) {
            throw new TarifClasseNonDefiniException($fraisId, $classeId, $anneeScolaireId);
        }

        return (float) $tarif->montant;
    }

    public function attacherFraisPourInscription(int $inscriptionId, int $classeId, int $anneeScolaireId, array $fraisDiversIds = []): void
    {
        DB::transaction(function () use ($inscriptionId, $classeId, $anneeScolaireId, $fraisDiversIds): void {
            // 1. Frais de scolarité — toujours appliqués, montant résolu
            // depuis la grille (lève TarifClasseNonDefiniException si
            // absente, jamais un montant à 0 par défaut).
            Frais::where('utilise_grille_tarifaire', true)
                ->get()
                ->each(function (Frais $frais) use ($inscriptionId, $classeId, $anneeScolaireId): void {
                    FraisEleve::create([
                        'inscription_id' => $inscriptionId,
                        'frais_id' => $frais->id,
                        'montant' => $this->getTarifClasse($frais->id, $classeId, $anneeScolaireId),
                        'statut' => 'du',
                    ]);
                });

            // 2. Frais divers sélectionnés par le parent — montant fixe.
            if ($fraisDiversIds !== []) {
                Frais::where('utilise_grille_tarifaire', false)
                    ->whereIn('id', $fraisDiversIds)
                    ->get()
                    ->each(function (Frais $frais) use ($inscriptionId): void {
                        FraisEleve::create([
                            'inscription_id' => $inscriptionId,
                            'frais_id' => $frais->id,
                            'montant' => $frais->montant,
                            'statut' => 'du',
                        ]);
                    });
            }
        });
    }

    public function previsualiserMontant(int $classeId, int $anneeScolaireId, array $fraisDiversIds = []): float
    {
        $totalScolarite = Frais::where('utilise_grille_tarifaire', true)
            ->get()
            ->sum(fn (Frais $frais): float => $this->getTarifClasse($frais->id, $classeId, $anneeScolaireId));

        $totalDivers = $fraisDiversIds === []
            ? 0.0
            : (float) Frais::where('utilise_grille_tarifaire', false)->whereIn('id', $fraisDiversIds)->sum('montant');

        return $totalScolarite + $totalDivers;
    }

    public function getMontantDu(int $inscriptionId): float
    {
        return (float) FraisEleve::where('inscription_id', $inscriptionId)
            ->where('statut', 'du')
            ->sum('montant');
    }

    public function getDetailFrais(int $inscriptionId): Collection
    {
        return FraisEleve::where('inscription_id', $inscriptionId)
            ->with('frais.categorie')
            ->get();
    }
}
