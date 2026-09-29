<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\RendezVousResource\Pages;

use App\Modules\Communication\Filament\Resources\RendezVousResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRendezVous extends ListRecords
{
    protected static string $resource = RendezVousResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
