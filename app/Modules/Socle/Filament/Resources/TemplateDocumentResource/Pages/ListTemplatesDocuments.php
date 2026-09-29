<?php

namespace App\Modules\Socle\Filament\Resources\TemplateDocumentResource\Pages;

use App\Modules\Socle\Filament\Resources\TemplateDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTemplatesDocuments extends ListRecords
{
    protected static string $resource = TemplateDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
