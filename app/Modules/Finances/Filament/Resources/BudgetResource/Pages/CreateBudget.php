<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\BudgetResource\Pages;

use App\Modules\Finances\Contracts\BudgetServiceContract;
use App\Modules\Finances\Filament\Resources\BudgetResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBudget extends CreateRecord
{
    protected static string $resource = BudgetResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(BudgetServiceContract::class)->creerBudgetPrevisionnel(
            (int) $data['annee_scolaire_id'],
            $data['lignes'],
        );
    }
}
