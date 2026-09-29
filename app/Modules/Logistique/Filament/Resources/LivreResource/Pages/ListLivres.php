<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Resources\LivreResource\Pages;

use App\Modules\Logistique\Filament\Resources\LivreResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLivres extends ListRecords
{
    protected static string $resource = LivreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
