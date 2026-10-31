<?php

namespace App\Modules\Communication\Filament\Resources\CommunicationTemplateResource\Pages;

use App\Modules\Communication\Filament\Resources\CommunicationTemplateResource;
use App\Modules\Communication\Services\WhatsAppTemplateService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCommunicationTemplate extends EditRecord
{
    protected static string $resource = CommunicationTemplateResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return self::fillModalData($data);
    }

    public static function fillModalData(array $data): array
    {
        $data['header_handle'] = null;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return self::updateFromModal($record, $data);
    }

    public static function updateFromModal(Model $record, array $data): Model
    {
        if (blank($data['header_handle'] ?? null)) {
            unset($data['header_handle']);
        }

        return app(WhatsAppTemplateService::class)->update($record, $data);
    }

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('view', ['record' => $this->record]);
    }
}
