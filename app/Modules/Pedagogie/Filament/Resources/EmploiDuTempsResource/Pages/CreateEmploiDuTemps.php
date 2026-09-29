<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource\Pages;

use App\Modules\Pedagogie\Contracts\EmploiDuTempsServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEmploiDuTemps extends CreateRecord
{
    protected static string $resource = EmploiDuTempsResource::class;

    /** La détection de conflit (ConflitEmploiDuTempsException) reste dans le Service. */
    protected function handleRecordCreation(array $data): Model
    {
        $cours = app(EmploiDuTempsServiceInterface::class)->planifierCours($data);

        return EmploiDuTemps::findOrFail($cours->id);
    }
}
