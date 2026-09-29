<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Services;

use App\Modules\Scolarite\Contracts\DossierEleveServiceContract;
use App\Modules\Scolarite\Exceptions\EleveIntrouvableException;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\FactureGeneree;
use App\Modules\Scolarite\Models\Paiement;
use App\Modules\Scolarite\Models\TableauHonneur;

/**
 * Vue d'agrégation pure lecture — ne stocke rien de nouveau, uniquement des
 * références vers des documents déjà générés par ce module ou d'autres
 * (Module 5 §1).
 */
class DossierEleveService implements DossierEleveServiceContract
{
    public function getDossierComplet(int $eleveId): object
    {
        $eleve = Eleve::with(['inscriptions.classe', 'parentsTuteurs'])->find($eleveId);

        if (! $eleve) {
            throw EleveIntrouvableException::pourId($eleveId);
        }

        $inscriptionIds = $eleve->inscriptions->pluck('id');

        return (object) [
            'eleve' => $eleve,
            'inscriptions' => $eleve->inscriptions,
            'factures' => FactureGeneree::whereIn('inscription_id', $inscriptionIds)->orderByDesc('date_emission')->get(),
            'recus' => Paiement::whereIn('inscription_id', $inscriptionIds)->where('statut', 'valide')->orderByDesc('created_at')->get(),
            'tableauxHonneur' => TableauHonneur::where('eleve_id', $eleveId)->orderByDesc('annee_scolaire_id')->get(),
            // Bulletins et certificats : dépendent respectivement du futur
            // module Évaluations et du module Documents scolaires (voir
            // README, "Modules du cahier des charges couverts ici") — ni
            // l'un ni l'autre n'est livré dans ce module. Placeholders
            // volontaires, pas un oubli : à brancher dès que leurs contracts
            // réels seront confirmés (même prudence que pour le module
            // Document de Socle).
            'bulletins' => [],
            'certificats' => [],
        ];
    }
}
