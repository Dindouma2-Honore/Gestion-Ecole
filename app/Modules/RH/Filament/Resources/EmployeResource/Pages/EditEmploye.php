<?php

namespace App\Modules\RH\Filament\Resources\EmployeResource\Pages;

use App\Modules\RH\Filament\Resources\EmployeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class EditEmploye extends EditRecord
{
    protected static string $resource = EmployeResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            if (! empty($data['role_id'])) {
                $data['poste'] = Role::query()->findOrFail($data['role_id'])->name;
            }
            $record->motifChangementPoste = $data['motif_changement_poste'] ?? null;
            $record->update($data);

            if ($record->user) {
                $record->user->update([
                    'name' => trim($record->nom.' '.$record->prenom),
                    'nom' => $record->nom,
                    'prenom' => $record->prenom,
                    'email' => $record->email,
                    'telephone' => $record->telephone,
                    'niveau_id' => $record->niveau_id,
                    'statut' => match ($record->statut) {
                        'actif', 'en_conge' => 'actif',
                        'suspendu' => 'suspendu',
                        default => 'desactive',
                    },
                ]);

                if ($record->role_id) {
                    $record->user->syncRoles([Role::query()->findOrFail($record->role_id)]);
                }
            }

            return $record;
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
