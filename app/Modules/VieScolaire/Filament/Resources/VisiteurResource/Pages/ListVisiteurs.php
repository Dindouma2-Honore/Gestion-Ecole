<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources\VisiteurResource\Pages;

use App\Modules\VieScolaire\Filament\Resources\VisiteurResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVisiteurs extends ListRecords
{
    protected static string $resource = VisiteurResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
