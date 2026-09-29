<?php

namespace App\Modules\Socle\Filament\Resources\FormatNumerotationResource\Pages;

use App\Modules\Socle\Filament\Resources\FormatNumerotationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFormatsNumerotation extends ListRecords
{
    protected static string $resource = FormatNumerotationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
