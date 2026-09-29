<?php

namespace App\Modules\Pedagogie\Filament\Resources\DisciplineEleveResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\DisciplineEleveResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDisciplineEleve extends CreateRecord
{
    protected static string $resource = DisciplineEleveResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['enregistre_par'] = auth()->id();

        return $data;
    }
}
