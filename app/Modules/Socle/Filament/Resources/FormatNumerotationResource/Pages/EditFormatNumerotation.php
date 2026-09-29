<?php

namespace App\Modules\Socle\Filament\Resources\FormatNumerotationResource\Pages;

use App\Modules\Socle\Filament\Resources\FormatNumerotationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFormatNumerotation extends EditRecord
{
    protected static string $resource = FormatNumerotationResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
