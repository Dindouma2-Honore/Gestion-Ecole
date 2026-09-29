<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\FraisResource\Pages;

use App\Modules\Scolarite\Contracts\FraisServiceContract;
use App\Modules\Scolarite\Filament\Resources\FraisResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditFrais extends EditRecord
{
    protected static string $resource = FraisResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(FraisServiceContract::class)->modifierFrais($record->id, $data);

        return $record->fresh();
    }
}
