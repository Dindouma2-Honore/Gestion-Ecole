<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages;

use App\Filament\Support\BusinessErrorPresenter;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\ParentTuteur;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use Throwable;

class CreateInscription extends CreateRecord
{
    protected static string $resource = InscriptionResource::class;

    #[Url]
    public ?int $eleve_id = null;

    protected function fillForm(): void
    {
        parent::fillForm();

        $eleveId = $this->eleve_id ?: request()->query('eleve_id') ?: request()->input('eleve_id');
        if ($eleveId) {
            $eleve = Eleve::query()->with('parentsTuteurs')->find($eleveId);
            if ($eleve) {
                $eleve->synchroniserParentTuteur();
                $eleve->load('parentsTuteurs');
                $parent = $eleve->parentsTuteurs
                    ->first(fn (ParentTuteur $parent): bool => (bool) $parent->pivot?->responsable_paiement)
                    ?? $eleve->parentsTuteurs->first();

                $this->form->fill([
                    'eleve_id' => (int) $eleveId,
                    'parent_id' => $parent?->id,
                    'lien_parente' => $parent?->pivot?->lien ?: 'tuteur',
                ]);
            }
        }
    }

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
        try {
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
        } catch (Throwable $exception) {
            if (! BusinessErrorPresenter::supports($exception)) {
                throw $exception;
            }

            $this->dispatch(
                'show-global-error',
                title: BusinessErrorPresenter::title($exception),
                message: $exception->getMessage(),
            );

            \Filament\Notifications\Notification::make()
                ->title(BusinessErrorPresenter::title($exception))
                ->body($exception->getMessage())
                ->danger()
                ->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }

        return Inscription::findOrFail($inscriptionId);
    }
}
