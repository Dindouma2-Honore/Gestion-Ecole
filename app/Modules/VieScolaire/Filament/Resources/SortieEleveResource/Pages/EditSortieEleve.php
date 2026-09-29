<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources\SortieEleveResource\Pages;

use App\Modules\VieScolaire\Filament\Resources\SortieEleveResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSortieEleve extends EditRecord
{
    protected static string $resource = SortieEleveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
