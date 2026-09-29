<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\UserResource\Pages;

use App\Modules\Socle\Filament\Resources\UserResource;
use App\Modules\Socle\Services\AssignationRoleService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->record->getRoleNames()->first();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['name'] = trim((string) ($data['name'] ?? '')) ?: trim($data['prenom'].' '.$data['nom']);

        if (! in_array($this->data['role'], ['Directeur', 'Enseignant'], true)) {
            $data['niveau_id'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $niveauId = ! empty($this->data['niveau_id']) ? (int) $this->data['niveau_id'] : null;

        app(AssignationRoleService::class)->assignerRole(
            $this->record,
            $this->data['role'],
            $niveauId,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
