<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\RubriqueDepenseResource\Pages;

use App\Modules\Finances\Contracts\GestionDepenseServiceContract;
use App\Modules\Finances\Filament\Resources\RubriqueDepenseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRubriqueDepense extends CreateRecord
{
    protected static string $resource = RubriqueDepenseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Model */
        return app(GestionDepenseServiceContract::class)->creerRubrique($data['nom']);
    }
}
