<?php

namespace App\Modules\Socle\Filament\Resources\TemplateDocumentResource\Pages;

use App\Modules\Socle\Filament\Resources\TemplateDocumentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTemplateDocument extends EditRecord
{
    protected static string $resource = TemplateDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
