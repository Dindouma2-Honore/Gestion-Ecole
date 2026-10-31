<?php

namespace App\Modules\Pedagogie\Filament\Resources\CategorieMatiereResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\CategorieMatiereResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCategorieMatiere extends CreateRecord
{
    protected static string $resource = CategorieMatiereResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return CategorieMatiereResource::enregistrer($data);
    }
}
