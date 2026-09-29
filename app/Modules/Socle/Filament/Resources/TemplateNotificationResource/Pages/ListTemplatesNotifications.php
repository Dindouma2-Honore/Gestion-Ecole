<?php

namespace App\Modules\Socle\Filament\Resources\TemplateNotificationResource\Pages;

use App\Modules\Socle\Filament\Resources\TemplateNotificationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTemplatesNotifications extends ListRecords
{
    protected static string $resource = TemplateNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
