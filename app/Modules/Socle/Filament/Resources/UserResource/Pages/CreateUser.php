<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\UserResource\Pages;

use App\Modules\Socle\Filament\Resources\UserResource;
use App\Modules\Socle\Services\AssignationRoleService;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['name'] = trim((string) ($data['name'] ?? '')) ?: trim($data['prenom'].' '.$data['nom']);

        if (! in_array($this->data['role'], ['Directeur', 'Enseignant'], true)) {
            $data['niveau_id'] = null;
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $rawPassword = ! empty($this->data['password']) ? (string) $this->data['password'] : null;

        /** @var \App\Models\User $record */
        $record = new (static::getModel())($data);
        if ($rawPassword) {
            $record->raw_temp_password = $rawPassword;
        }
        $record->save();

        return $record;
    }

    protected function afterCreate(): void
    {
        $niveauId = ! empty($this->data['niveau_id']) ? (int) $this->data['niveau_id'] : null;

        app(AssignationRoleService::class)->assignerRole(
            $this->record,
            $this->data['role'],
            $niveauId,
        );
    }
}
