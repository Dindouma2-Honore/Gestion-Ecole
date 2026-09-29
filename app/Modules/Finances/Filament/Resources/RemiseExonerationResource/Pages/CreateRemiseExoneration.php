<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\RemiseExonerationResource\Pages;

use App\Modules\Finances\Contracts\FraisScolaireServiceContract;
use App\Modules\Finances\Filament\Resources\RemiseExonerationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRemiseExoneration extends CreateRecord
{
    protected static string $resource = RemiseExonerationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['valeur'] = $data['type'] === 'exoneration_totale' ? 0 : (float) $data['valeur'];

        return app(FraisScolaireServiceContract::class)->accorderRemise(
            (int) $data['eleve_id'],
            $data['type_frais'],
            $data['type'],
            (float) $data['valeur'],
            $data['motif'],
        );
    }
}
