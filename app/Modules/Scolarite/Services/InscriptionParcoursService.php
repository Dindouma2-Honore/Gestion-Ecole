<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Services;

use App\Modules\Finances\Contracts\FacturePreinscriptionServiceContract;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\ParentTuteur;
use Illuminate\Support\Facades\DB;

class InscriptionParcoursService
{
    public function __construct(
        private readonly InscriptionServiceInterface $inscriptions,
        private readonly FacturePreinscriptionServiceContract $factures,
    ) {}

    /**
     * Parcours UI unique : parent payeur, préinscription, facture, et
     * éventuellement confirmation du versement — dans une seule transaction.
     */
    public function inscrireEleveAdmis(
        Eleve $eleve,
        int $classeId,
        int $anneeScolaireId,
        array $fraisOptionnels = [],
        ?float $montantVerse = null,
        bool $validerPaiement = false,
        string $modePaiement = 'especes',
        ?string $referenceTransaction = null,
    ): int {
        return DB::transaction(function () use (
            $eleve,
            $classeId,
            $anneeScolaireId,
            $fraisOptionnels,
            $montantVerse,
            $validerPaiement,
            $modePaiement,
            $referenceTransaction,
        ): int {
            $parent = $this->assurerParentPayeur($eleve);

            $inscriptionId = $this->inscriptions->demarrerPreinscription(
                eleveId: $eleve->id,
                classeId: $classeId,
                anneeScolaireId: $anneeScolaireId,
                parentId: $parent->id,
                fraisOptionnels: $fraisOptionnels,
                montantVerse: $montantVerse,
            );

            if ($validerPaiement) {
                $facture = FacturePreinscription::query()->where('inscription_id', $inscriptionId)->first();
                if ($facture) {
                    $this->factures->confirmerVersement(
                        factureId: $facture->id,
                        mode: $modePaiement,
                        referenceTransaction: $referenceTransaction,
                        montant: $montantVerse,
                    );
                }
            }

            return $inscriptionId;
        });
    }

    private function assurerParentPayeur(Eleve $eleve): ParentTuteur
    {
        $parent = $eleve->synchroniserParentTuteur();
        if ($parent) {
            return $parent;
        }

        $parent = $eleve->parentsTuteurs()->firstOrCreate(
            ['nom' => trim((string) $eleve->nom_tuteur) ?: "Parent de {$eleve->nom} {$eleve->prenom}"],
            [
                'prenom' => '',
                'telephone' => $eleve->telephone_tuteur,
                'email' => $eleve->email,
                'portail_actif' => true,
            ]
        );

        $eleve->parentsTuteurs()->syncWithoutDetaching([
            $parent->id => [
                'lien' => 'tuteur',
                'responsable_legal' => true,
                'responsable_paiement' => true,
                'autorise_recuperation' => true,
            ],
        ]);

        return $parent;
    }
}
