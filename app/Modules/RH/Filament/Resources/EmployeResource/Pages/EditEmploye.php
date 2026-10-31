<?php

namespace App\Modules\RH\Filament\Resources\EmployeResource\Pages;

use App\Modules\RH\Filament\Resources\EmployeResource;
use App\Modules\RH\Models\PosteAdministratif;
use App\Modules\Socle\Contracts\UtilisateurServiceInterface;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditEmploye extends EditRecord
{
    protected static string $resource = EmployeResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return self::updatePersonnel($record, $data);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erreur lors de la modification')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }

    public static function updatePersonnel(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            if (! empty($data['poste_administratif_id'])) {
                $libellePoste = PosteAdministratif::query()->where('id', $data['poste_administratif_id'])->value('nom');
                if (filled($libellePoste)) {
                    $data['poste'] = $libellePoste;
                }
            }
            $record->motifChangementPoste = $data['motif_changement_poste'] ?? null;
            unset($data['motif_changement_poste']);
            $record->update($data);

            $libellePoste = $record->posteAdministratif()->value('nom');
            if (filled($libellePoste) && $record->poste !== $libellePoste) {
                $record->forceFill(['poste' => $libellePoste])->saveQuietly();
            }

            if ($record->user_id) {
                $email = filled($record->email) ? mb_strtolower(trim((string) $record->email)) : null;

                app(UtilisateurServiceInterface::class)->mettreAJourIdentite((int) $record->user_id, [
                    'name' => trim($record->nom.' '.$record->prenom),
                    'nom' => $record->nom,
                    'prenom' => $record->prenom,
                    'email' => $email,
                    'telephone' => $record->telephone,
                    'niveau_id' => $record->niveau_id,
                ]);

                if ($record->role_id) {
                    app(UtilisateurServiceInterface::class)->synchroniserRoles((int) $record->user_id, (int) $record->role_id);
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
