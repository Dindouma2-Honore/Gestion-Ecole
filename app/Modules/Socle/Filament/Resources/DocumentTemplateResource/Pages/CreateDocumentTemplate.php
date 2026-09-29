<?php

namespace App\Modules\Socle\Filament\Resources\DocumentTemplateResource\Pages;

use App\Modules\Socle\Contracts\DocumentTemplateServiceContract;
use App\Modules\Socle\Filament\Resources\DocumentTemplateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDocumentTemplate extends CreateRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $code = $data['code'];
        unset($data['code']);

        return app(DocumentTemplateServiceContract::class)->publierNouvelleVersion($code, $data, auth()->id());
    }
}
