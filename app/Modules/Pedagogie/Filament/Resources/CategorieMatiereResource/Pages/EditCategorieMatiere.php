<?php

namespace App\Modules\Pedagogie\Filament\Resources\CategorieMatiereResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\CategorieMatiereResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategorieMatiere extends EditRecord
{
    protected static string $resource = CategorieMatiereResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
