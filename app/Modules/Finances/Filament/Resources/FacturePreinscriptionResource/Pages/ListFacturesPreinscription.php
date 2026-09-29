<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\FacturePreinscriptionResource\Pages;

use App\Modules\Finances\Filament\Resources\FacturePreinscriptionResource;
use Filament\Resources\Pages\ListRecords;

class ListFacturesPreinscription extends ListRecords
{
    protected static string $resource = FacturePreinscriptionResource::class;
}
