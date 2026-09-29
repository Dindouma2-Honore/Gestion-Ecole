<?php

namespace App\Modules\Assiduite\Filament\Resources\VisiteInfirmerieResource\Pages;

use App\Modules\Assiduite\Filament\Resources\VisiteInfirmerieResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVisitesInfirmerie extends ListRecords
{
    protected static string $resource = VisiteInfirmerieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
