<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\CategorieFraisResource\Pages;

use App\Modules\Scolarite\Contracts\FraisServiceContract;
use App\Modules\Scolarite\Filament\Resources\CategorieFraisResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCategorieFrais extends EditRecord
{
    protected static string $resource = CategorieFraisResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(FraisServiceContract::class)->modifierCategorie($record->id, $data['nom']);

        return $record->fresh();
    }
}
