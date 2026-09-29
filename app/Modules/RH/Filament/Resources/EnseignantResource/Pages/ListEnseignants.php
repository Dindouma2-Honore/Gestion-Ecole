<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources\EnseignantResource\Pages;

use App\Filament\Concerns\HasCardListLayout;
use App\Modules\RH\Filament\Resources\EnseignantResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEnseignants extends ListRecords
{
    use HasCardListLayout;

    protected static string $resource = EnseignantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->cardLayoutAction(),
            Actions\CreateAction::make(),
        ];
    }
}
