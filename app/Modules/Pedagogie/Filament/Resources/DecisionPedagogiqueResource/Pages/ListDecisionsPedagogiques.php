<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\DecisionPedagogiqueResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\DecisionPedagogiqueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDecisionsPedagogiques extends ListRecords
{
    protected static string $resource = DecisionPedagogiqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nouvelle décision'),
        ];
    }
}
