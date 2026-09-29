<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\AnomalieAppelResource\Pages;

use App\Modules\Pedagogie\Filament\Resources\AnomalieAppelResource;
use Filament\Resources\Pages\ListRecords;

class ListAnomalieAppels extends ListRecords
{
    protected static string $resource = AnomalieAppelResource::class;
}
