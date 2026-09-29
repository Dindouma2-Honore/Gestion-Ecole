<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources\PersonnelRoleResource\Pages;

use App\Modules\RH\Filament\Resources\PersonnelRoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPersonnelRole extends EditRecord
{
    protected static string $resource = PersonnelRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->disabled(fn (): bool => $this->record->users()->exists())->tooltip(fn (): ?string => $this->record->users()->exists() ? 'Ce rôle est encore attribué.' : null)];
    }
}
