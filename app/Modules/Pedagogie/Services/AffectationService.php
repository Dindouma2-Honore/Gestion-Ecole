<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Exceptions\EnseignantPrincipalDejaAffecteException;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AffectationService
{
    public function affecterEnseignantPrincipal(
        int $enseignantId,
        int $classeId,
        int $anneeScolaireId,
        bool $remplacerSiExistant = false
    ): AffectationPedagogique {
        return DB::transaction(function () use ($enseignantId, $classeId, $anneeScolaireId, $remplacerSiExistant): AffectationPedagogique {
            $existant = AffectationPedagogique::query()
                ->principal()
                ->actif()
                ->where('classe_id', $classeId)
                ->where('annee_scolaire_id', $anneeScolaireId)
                ->first();

            if ($existant) {
                if (! $remplacerSiExistant && $existant->enseignant_id !== $enseignantId) {
                    throw new EnseignantPrincipalDejaAffecteException;
                }

                $existant->update(['actif' => false]);
            }

            $affectation = AffectationPedagogique::updateOrCreate(
                [
                    'classe_id' => $classeId,
                    'annee_scolaire_id' => $anneeScolaireId,
                    'type_affectation' => AffectationPedagogique::TYPE_PRINCIPAL,
                    'actif' => true,
                ],
                [
                    'enseignant_id' => $enseignantId,
                    'matiere_id' => null,
                    'offre_pedagogique_id' => null,
                    'role_precision' => 'Enseignant principal',
                ]
            );

            // Synchronisation de confort avec la table classes si existante
            if (DB::getSchemaBuilder()->hasTable('classes') && DB::getSchemaBuilder()->hasColumn('classes', 'professeur_principal_id')) {
                DB::table('classes')->where('id', $classeId)->update(['professeur_principal_id' => $enseignantId]);
            }

            return $affectation;
        });
    }

    public function affecterAssistant(
        int $enseignantId,
        int $classeId,
        int $anneeScolaireId,
        ?int $matiereId = null,
        ?string $rolePrecision = null
    ): AffectationPedagogique {
        return AffectationPedagogique::create([
            'classe_id' => $classeId,
            'annee_scolaire_id' => $anneeScolaireId,
            'enseignant_id' => $enseignantId,
            'type_affectation' => AffectationPedagogique::TYPE_ASSISTANT,
            'matiere_id' => $matiereId,
            'role_precision' => $rolePrecision ?? ($matiereId ? 'Assistant spécialisé' : 'Assistant généraliste'),
            'actif' => true,
        ]);
    }

    public function affecterEnseignantMatiere(
        int $enseignantId,
        int $classeId,
        int $matiereId,
        int $anneeScolaireId,
        ?float $coefficient = null,
        ?int $offrePedagogiqueId = null
    ): AffectationPedagogique {
        return AffectationPedagogique::updateOrCreate(
            [
                'classe_id' => $classeId,
                'matiere_id' => $matiereId,
                'annee_scolaire_id' => $anneeScolaireId,
                'type_affectation' => AffectationPedagogique::TYPE_MATIERE,
            ],
            [
                'enseignant_id' => $enseignantId,
                'offre_pedagogique_id' => $offrePedagogiqueId,
                'coefficient' => $coefficient,
                'role_precision' => 'Enseignant de matière',
                'actif' => true,
            ]
        );
    }

    public function getEnseignantPrincipal(int $classeId, int $anneeScolaireId): ?AffectationPedagogique
    {
        return AffectationPedagogique::query()
            ->principal()
            ->actif()
            ->where('classe_id', $classeId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->first();
    }

    public function getAssistants(int $classeId, int $anneeScolaireId): Collection
    {
        return AffectationPedagogique::query()
            ->assistants()
            ->actif()
            ->where('classe_id', $classeId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->get();
    }

    public function getAffectationsClasse(int $classeId, int $anneeScolaireId): Collection
    {
        return AffectationPedagogique::query()
            ->actif()
            ->where('classe_id', $classeId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->get();
    }

    public function getAffectationsEnseignant(int $enseignantId, int $anneeScolaireId): Collection
    {
        return AffectationPedagogique::query()
            ->actif()
            ->where('enseignant_id', $enseignantId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->get();
    }

    public function retirerAffectation(int $affectationId): bool
    {
        $affectation = AffectationPedagogique::find($affectationId);

        if (! $affectation) {
            return false;
        }

        return (bool) $affectation->update(['actif' => false]);
    }
}
