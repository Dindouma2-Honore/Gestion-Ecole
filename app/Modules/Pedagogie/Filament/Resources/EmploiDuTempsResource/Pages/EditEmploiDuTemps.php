<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource\Pages;

use App\Modules\Pedagogie\Contracts\EmploiDuTempsServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEmploiDuTemps extends EditRecord
{
    protected static string $resource = EmploiDuTempsResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(EmploiDuTempsServiceInterface::class)->modifierCours($record->id, $data);
    }
}
