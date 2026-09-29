<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\RubriqueDepenseResource\Pages;

use App\Modules\Finances\Filament\Resources\RubriqueDepenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRubriquesDepense extends ListRecords
{
    protected static string $resource = RubriqueDepenseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
