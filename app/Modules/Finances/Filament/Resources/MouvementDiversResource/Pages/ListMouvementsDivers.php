<?php

namespace App\Modules\Finances\Filament\Resources\MouvementDiversResource\Pages;

use App\Modules\Finances\Filament\Resources\MouvementDiversResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMouvementsDivers extends ListRecords
{
    protected static string $resource = MouvementDiversResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('finance_misc.add'))];
    }
}
