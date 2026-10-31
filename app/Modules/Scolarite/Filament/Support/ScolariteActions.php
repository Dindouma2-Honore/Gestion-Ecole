<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Support;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Scolarite\Contracts\FraisServiceContract;
use App\Modules\Scolarite\Exceptions\CategorieFraisEnUsageException;
use App\Modules\Scolarite\Models\CategorieFrais;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Frais;
use App\Modules\Scolarite\Models\GrilleTarifaire;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\ParentTuteur;
use App\Modules\Scolarite\Models\TransfertAffectation;
use App\Modules\Scolarite\Services\ClasseConfigurationService;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Filament\Support\BusinessErrorPresenter;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Standardise les opérations de création, modification, consultation et suppression
 * pour l'ensemble des ressources du module Scolarité selon le socle visuel ERP.
 */
final class ScolariteActions
{
    public static function create(): CreateAction
    {
        return AmbassadorsDesign::goldenFormModal(CreateAction::make())
            ->createAnother(false)
            ->schema(fn (Schema $schema, $livewire): Schema => $livewire::getResource()::form($schema->operation('create')))
            ->using(function (array $data, string $model, CreateAction $action): Model {
                try {
                    return DB::transaction(function () use ($data, $model, $action): Model {
                        return match (class_basename($model)) {
                            'Eleve' => self::createEleve($data),
                            'Inscription' => self::createInscription($data),
                            'Classe' => self::createClasse($data, $action->getRawData()),
                            'CategorieFrais' => app(FraisServiceContract::class)->creerCategorie($data['nom']),
                            'Frais' => self::createFrais($data),
                            'GrilleTarifaire' => self::createTarif($data),
                            'TransfertAffectation' => self::createTransfert($data),
                            default => $model::create($data),
                        };
                    });
                } catch (\Throwable $e) {
                    if (BusinessErrorPresenter::supports($e)) {
                        Notification::make()
                            ->title(BusinessErrorPresenter::title($e))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        throw (new Halt)->rollBackDatabaseTransaction();
                    }

                    throw $e;
                }
            });
    }

    public static function edit(): EditAction
    {
        return AmbassadorsDesign::goldenFormModal(EditAction::make())
            ->mutateRecordDataUsing(function (Model $record, array $data): array {
                if ($record instanceof Classe) {
                    return [...$data, ...app(ClasseConfigurationService::class)->donnees($record)];
                }

                if ($record instanceof Eleve) {
                    $parent = $record->parentsTuteurs()
                        ->wherePivot('responsable_paiement', true)
                        ->first()
                        ?? $record->parentsTuteurs()->first();

                    if ($parent) {
                        $data['nom_tuteur'] = filled($data['nom_tuteur'] ?? null) ? $data['nom_tuteur'] : trim("{$parent->nom} {$parent->prenom}");
                        $data['telephone_tuteur'] = filled($data['telephone_tuteur'] ?? null) ? $data['telephone_tuteur'] : $parent->telephone;
                    }
                }

                return $data;
            })
            ->extraModalFooterActions(fn (Model $record, $livewire): array => [
                self::modalDelete($record, $livewire::getResource()),
            ])
            ->using(function (Model $record, array $data, EditAction $action): Model {
                try {
                    return DB::transaction(function () use ($record, $data, $action): Model {
                        return match (class_basename($record)) {
                            'Classe' => self::updateClasse($record, $data, $action->getRawData()),
                            'CategorieFrais' => self::updateCategorieFrais($record, $data),
                            'Frais' => self::updateFrais($record, $data),
                            'GrilleTarifaire' => self::updateTarif($record, $data),
                            'Eleve' => self::updateEleve($record, $data),
                            default => tap($record)->update($data),
                        };
                    });
                } catch (\Throwable $e) {
                    if (BusinessErrorPresenter::supports($e)) {
                        Notification::make()
                            ->title(BusinessErrorPresenter::title($e))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        throw (new Halt)->rollBackDatabaseTransaction();
                    }

                    throw $e;
                }
            });
    }

    public static function view(): ViewAction
    {
        return AmbassadorsDesign::goldenFormModal(ViewAction::make())
            ->extraModalFooterActions(function (Model $record, $livewire): array {
                $resource = $livewire::getResource();
                $actions = [];

                if ($resource::canEdit($record)) {
                    $actions[] = self::edit()->label('Modifier')->record($record)
                        ->schema(fn (Schema $schema): Schema => $resource::form($schema->model($record)->operation('edit')))
                        ->cancelParentActions();
                }

                if ($resource::canDelete($record)) {
                    $actions[] = self::modalDelete($record, $resource);
                }

                return $actions;
            });
    }

    public static function delete(): DeleteAction
    {
        return AmbassadorsDesign::goldenDialogModal(DeleteAction::make())
            ->using(function (Model $record): void {
                if ($record instanceof CategorieFrais) {
                    try {
                        app(FraisServiceContract::class)->supprimerCategorie($record->id);

                        return;
                    } catch (CategorieFraisEnUsageException $e) {
                        Notification::make()->title('Suppression impossible')->body($e->getMessage())->danger()->send();
                        throw (new Halt)->rollBackDatabaseTransaction();
                    }
                }

                if ($record instanceof Frais) {
                    app(FraisServiceContract::class)->supprimerFrais($record->id);

                    return;
                }

                if ($record instanceof ParentTuteur && $record->eleves()->exists()) {
                    Notification::make()
                        ->title('Suppression impossible')
                        ->body('Ce parent ou tuteur est rattaché à un ou plusieurs élèves.')
                        ->danger()
                        ->send();

                    throw (new Halt)->rollBackDatabaseTransaction();
                }

                $record->delete();
            });
    }

    public static function modalDelete(Model $record, string $resource): DeleteAction
    {
        return self::delete()->label('Supprimer')->record($record)
            ->authorize(fn (): bool => $resource::canDelete($record))
            ->disabled(fn (): bool => match (class_basename($record)) {
                'Classe' => $record->inscriptions()->exists(),
                'CategorieFrais' => $record->frais()->exists(),
                'Frais' => (bool) $record->utilise_grille_tarifaire && $record->grilleTarifaire()->exists(),
                'Eleve' => $record->inscriptions()->whereNotIn('statut', ['annulee'])->exists(),
                'ParentTuteur' => $record->eleves()->exists(),
                default => false,
            })
            ->cancelParentActions();
    }

    public static function configureTable(Table $table, ?string $recordAction = 'edit'): Table
    {
        return AmbassadorsDesign::configureTable($table, $recordAction);
    }

    public static function tableActionGroup(array $actions): ActionGroup
    {
        return AmbassadorsDesign::tableActionGroup($actions);
    }

    private static function createEleve(array $data): Model
    {
        $resultat = app(EleveServiceInterface::class)->creer($data);

        return Eleve::findOrFail($resultat['id']);
    }

    private static function updateEleve(Eleve $eleve, array $data): Eleve
    {
        $eleve->update($data);
        $eleve->synchroniserParentTuteur();

        return $eleve;
    }

    private static function createInscription(array $data): Inscription
    {
        $inscriptionId = app(\App\Modules\Scolarite\Contracts\InscriptionServiceInterface::class)->preparerEtDemarrerPreinscription(
            eleveId: filled($data['eleve_id'] ?? null) ? (int) $data['eleve_id'] : null,
            eleve: $data['eleve'] ?? [],
            parentId: filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null,
            parent: $data['parent'] ?? [],
            lienParente: $data['lien_parente'] ?? 'tuteur',
            classeId: (int) $data['classe_id'],
            anneeScolaireId: (int) $data['annee_scolaire_id'],
            fraisOptionnels: array_values($data['frais_optionnels'] ?? []),
            montantVerse: filled($data['montant_verse'] ?? null) ? (float) $data['montant_verse'] : null,
        );

        return Inscription::findOrFail($inscriptionId);
    }

    private static function createClasse(array $data, array $rawData): Classe
    {
        $data['frais'] = (float) config('scolarite.frais_inscription', 15_000);
        $classe = Classe::create($data);
        app(ClasseConfigurationService::class)->enregistrer($classe, $rawData);

        return $classe;
    }

    private static function updateClasse(Classe $classe, array $data, array $rawData): Classe
    {
        $data['frais'] = (float) config('scolarite.frais_inscription', 15_000);
        $classe->update($data);
        app(ClasseConfigurationService::class)->enregistrer($classe, $rawData);

        return $classe;
    }

    private static function createFrais(array $data): object
    {
        return app(FraisServiceContract::class)->creerFrais(
            nom: $data['nom'],
            montant: (float) ($data['montant'] ?? 0),
            categorieId: (int) $data['categorie_frais_id'],
            utiliseGrilleTarifaire: (bool) ($data['utilise_grille_tarifaire'] ?? false),
            ordreRepartition: (int) ($data['ordre_repartition'] ?? 999),
        );
    }

    private static function updateFrais(Frais $frais, array $data): Frais
    {
        app(FraisServiceContract::class)->modifierFrais($frais->id, $data);

        return $frais->fresh();
    }

    private static function updateCategorieFrais(CategorieFrais $categorie, array $data): CategorieFrais
    {
        app(FraisServiceContract::class)->modifierCategorie($categorie->id, $data['nom']);

        return $categorie->fresh();
    }

    private static function createTarif(array $data): GrilleTarifaire
    {
        app(FraisServiceContract::class)->definirTarifClasse(
            fraisId: (int) $data['frais_id'],
            classeId: (int) $data['classe_id'],
            anneeScolaireId: (int) $data['annee_scolaire_id'],
            montant: (float) $data['montant'],
        );

        return GrilleTarifaire::where('frais_id', $data['frais_id'])
            ->where('classe_id', $data['classe_id'])
            ->where('annee_scolaire_id', $data['annee_scolaire_id'])
            ->firstOrFail();
    }

    private static function updateTarif(GrilleTarifaire $record, array $data): GrilleTarifaire
    {
        app(FraisServiceContract::class)->definirTarifClasse(
            fraisId: (int) $data['frais_id'],
            classeId: (int) $data['classe_id'],
            anneeScolaireId: (int) $data['annee_scolaire_id'],
            montant: (float) $data['montant'],
        );

        return $record->fresh();
    }

    private static function createTransfert(array $data): TransfertAffectation
    {
        $inscription = Inscription::query()->lockForUpdate()->findOrFail($data['inscription_id']);
        $destination = Classe::query()->findOrFail($data['classe_destination_id']);

        if ((int) $destination->annee_scolaire_id !== (int) $inscription->annee_scolaire_id) {
            throw ValidationException::withMessages(['classe_destination_id' => 'La nouvelle classe doit appartenir à la même année scolaire.']);
        }
        if ((int) $destination->id === (int) $inscription->classe_id) {
            throw ValidationException::withMessages(['classe_destination_id' => 'Choisissez une classe différente de la classe actuelle.']);
        }

        $effectif = Inscription::query()
            ->where('classe_id', $destination->id)
            ->whereNotIn('statut', ['annulee'])
            ->count();
        if ($effectif >= (int) $destination->capacite_max) {
            throw ValidationException::withMessages(['classe_destination_id' => 'Cette classe a atteint sa capacité maximale.']);
        }

        $transfert = TransfertAffectation::query()->create([
            'inscription_id' => $inscription->id,
            'eleve_id' => $inscription->eleve_id,
            'classe_origine_id' => $inscription->classe_id,
            'classe_destination_id' => $destination->id,
            'annee_scolaire_id' => $inscription->annee_scolaire_id,
            'date_transfert' => $data['date_transfert'],
            'motif' => $data['motif'],
            'effectue_par' => auth()->id(),
        ]);

        $inscription->update(['classe_id' => $destination->id, 'type' => 'transfert']);

        app(AuditServiceContract::class)->enregistrerAvecMotif(
            $transfert,
            "Transfert de l'élève #{$inscription->eleve_id} vers la classe #{$destination->id}",
            $data['motif'],
        );

        return $transfert;
    }
}
