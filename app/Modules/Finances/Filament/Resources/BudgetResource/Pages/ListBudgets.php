<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\BudgetResource\Pages;

use App\Modules\Finances\Filament\Resources\BudgetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBudgets extends ListRecords
{
    protected static string $resource = BudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
