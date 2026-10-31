<?php

namespace App\Modules\RH\Filament\Support;

use App\Filament\Support\AmbassadorsDesign;
use App\Modules\RH\Contracts\ContratServiceInterface;
use App\Modules\RH\Contracts\EmployeServiceContract;
use App\Modules\RH\Contracts\PaieServiceContract;
use App\Modules\RH\Filament\Resources\ContratResource\Pages\EditContrat;
use App\Modules\RH\Filament\Resources\EmployeResource\Pages\EditEmploye;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Modal actions scoped to HR; resource authorization remains handled by Filament. */
final class RhActions
{
    public static function create(): CreateAction
    {
        return AmbassadorsDesign::goldenFormModal(CreateAction::make())
            ->createAnother(false)
            ->schema(fn (Schema $schema, $livewire): Schema => $livewire::getResource()::form($schema->operation('create')))
            ->using(function (array $data, string $model): Model {
                return DB::transaction(function () use ($data, $model): Model {
                    switch (class_basename($model)) {
                        case 'Employe':
                            return app(EmployeServiceContract::class)->embaucher($data)['employe'];
                        case 'Contrat':
                            $data['date_debut'] = $data['date_debut'] ?? today()->toDateString();
                            if (($data['type'] ?? null) !== 'CDI' && empty($data['date_fin'])) {
                                try {
                                    $data['date_fin'] = app(AnneeScolaireServiceContract::class)->getAnneeCourante()->date_fin->toDateString();
                                } catch (\Throwable) {
                                    // Pas d'année scolaire active trouvée
                                }
                            }
                            if (($data['categorie_paie'] ?? null) === 'horaire' && empty($data['salaire_base'])) {
                                $data['salaire_base'] = 0;
                            }

                            $contratActif = Contrat::where('employe_id', $data['employe_id'])
                                ->where('statut', 'actif')
                                ->first();

                            if ($contratActif) {
                                $dateDebut = Carbon::parse($data['date_debut'] ?? today());
                                $updateData = ['statut' => 'expire'];
                                if ($contratActif->date_fin === null || $contratActif->date_fin->gte($dateDebut)) {
                                    $updateData['date_fin'] = $dateDebut->copy()->subDay();
                                }
                                $contratActif->update($updateData);
                            }

                            $contrat = app(ContratServiceInterface::class)->creerContrat($data);
                            Employe::where('id', $data['employe_id'])->update(['contrat_id' => $contrat->id]);

                            return $contrat;
                        case 'AvanceSalaire':
                            return app(PaieServiceContract::class)->demanderAvance((int) $data['employe_id'], (float) $data['montant'], $data['motif'], (bool) ($data['derogation_plafond'] ?? false), $data['motif_derogation'] ?? null);
                        case 'AbsencePersonnel':
                        case 'PersonnelPrime':
                        case 'SanctionPersonnel':
                            $data['created_by'] = auth()->id();
                            break;
                        case 'Prime':
                            $data['proposee_par'] = auth()->id();
                            break;
                        case 'Role':
                            $data['guard_name'] = 'web';
                            break;
                    }

                    return $model::create($data);
                });
            });
    }

    public static function edit(): EditAction
    {
        return AmbassadorsDesign::goldenFormModal(EditAction::make())
            ->extraModalFooterActions(fn (Model $record, $livewire): array => [self::modalDelete($record, $livewire::getResource())])
            ->using(function (Model $record, array $data, EditAction $action): Model {
                return DB::transaction(function () use ($record, $data, $action): Model {
                    if ($record instanceof Employe) {
                        return EditEmploye::updatePersonnel($record, $data);
                    }
                    $record->update($data);
                    if ($record instanceof Contrat) {
                        EditContrat::saveFonction($record, $action->getRawData());
                    }

                    return $record;
                });
            });
    }

    public static function view(): ViewAction
    {
        return self::withRecordControls(AmbassadorsDesign::goldenFormModal(ViewAction::make()));
    }

    public static function withRecordControls(Action $action): Action
    {
        return $action->extraModalFooterActions(function (Model $record, $livewire): array {
            $resource = $livewire::getResource();

            return [
                self::edit()->label('Modifier')->record($record)
                    ->authorize(fn (): bool => $resource::canEdit($record))
                    ->schema(fn (Schema $schema): Schema => $resource::form($schema->model($record)->operation('edit')))
                    ->cancelParentActions(),
                self::modalDelete($record, $resource),
            ];
        });
    }

    private static function modalDelete(Model $record, string $resource): DeleteAction
    {
        return self::delete()->label('Supprimer')->record($record)
            ->authorize(fn (): bool => $resource::canDelete($record))
            ->disabled(fn (): bool => match (class_basename($record)) {
                'Role' => $record->users()->exists(),
                'CategoriePersonnel', 'PosteAdministratif' => $record->employes()->exists(),
                default => false,
            })
            ->cancelParentActions();
    }

    public static function delete(): DeleteAction
    {
        return AmbassadorsDesign::goldenDialogModal(DeleteAction::make());
    }
}
