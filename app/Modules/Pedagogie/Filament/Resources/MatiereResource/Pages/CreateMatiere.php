<?php

namespace App\Modules\Pedagogie\Filament\Resources\MatiereResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\MatiereResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMatiere extends CreateRecord
{
    protected static string $resource = MatiereResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return MatiereResource::enregistrer($data);
    }
}
