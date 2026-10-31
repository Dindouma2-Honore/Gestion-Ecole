<?php

namespace App\Modules\Communication\Filament\Resources\CommunicationTemplateResource\Pages;

use App\Modules\Communication\Filament\Resources\CommunicationTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCommunicationTemplate extends ViewRecord
{
    protected static string $resource = CommunicationTemplateResource::class;

    protected string $view = 'communication::templates.detail';

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), ...CommunicationTemplateResource::workflowActions()];
    }
}
