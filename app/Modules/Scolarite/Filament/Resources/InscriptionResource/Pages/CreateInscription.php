<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages;

use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Scolarite\Models\Inscription;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateInscription extends CreateRecord
{
    protected static string $resource = InscriptionResource::class;

    /**
     * Toute la règle métier (matricule, capacité de la classe, double
     * inscription, frais dus, facture provisoire) vit dans
     * InscriptionServiceInterface::inscrire() — Filament ne fait que
     * collecter les champs et déléguer. Le circuit de facturation est
     * entièrement interne à Scolarité, sans passer par un module Finances
     * (Module 5 §1).
     */
    protected function handleRecordCreation(array $data): Model
    {
        $inscriptionId = app(InscriptionServiceInterface::class)->preparerEtDemarrerPreinscription(
            eleveId: filled($data['eleve_id'] ?? null) ? (int) $data['eleve_id'] : null,
            eleve: $data['eleve'] ?? [],
            parentId: filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null,
            parent: $data['parent'] ?? [],
            lienParente: $data['lien_parente'],
            classeId: (int) $data['classe_id'],
            anneeScolaireId: (int) $data['annee_scolaire_id'],
            fraisOptionnels: array_values($data['frais_optionnels'] ?? []),
            montantVerse: filled($data['montant_verse'] ?? null) ? (float) $data['montant_verse'] : null,
        );

        return Inscription::findOrFail($inscriptionId);
    }
}
