<?php

namespace App\Modules\Assiduite\Filament\Resources\VisiteInfirmerieResource\Pages;

use App\Modules\Assiduite\Filament\Resources\VisiteInfirmerieResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVisiteInfirmerie extends EditRecord
{
    protected static string $resource = VisiteInfirmerieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
