<?php

namespace App\Modules\Communication\Filament\Resources\CommunicationTemplateResource\Pages;

use App\Modules\Communication\Filament\Resources\CommunicationTemplateResource;
use App\Modules\Communication\Services\WhatsAppTemplateService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCommunicationTemplate extends CreateRecord
{
    protected static string $resource = CommunicationTemplateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(WhatsAppTemplateService::class)->create($data);
    }

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('view', ['record' => $this->record]);
    }
}
