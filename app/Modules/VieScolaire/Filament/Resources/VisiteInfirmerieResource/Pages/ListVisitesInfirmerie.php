<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources\VisiteInfirmerieResource\Pages;

use App\Modules\VieScolaire\Filament\Resources\VisiteInfirmerieResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListVisitesInfirmerie extends ListRecords
{
    protected static string $resource = VisiteInfirmerieResource::class;
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouvelle visite'),
        ];
    }
}
