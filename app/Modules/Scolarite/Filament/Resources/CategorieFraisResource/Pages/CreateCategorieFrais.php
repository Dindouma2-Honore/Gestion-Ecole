<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\CategorieFraisResource\Pages;

use App\Modules\Scolarite\Contracts\FraisServiceContract;
use App\Modules\Scolarite\Filament\Resources\CategorieFraisResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCategorieFrais extends CreateRecord
{
    protected static string $resource = CategorieFraisResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(FraisServiceContract::class)->creerCategorie($data['nom']);
    }
}
