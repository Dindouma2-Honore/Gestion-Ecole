<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\MessageParentResource\Pages;

use App\Modules\Communication\Contracts\CommunicationParentServiceContract;
use App\Modules\Communication\Filament\Resources\MessageParentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class CreateMessageParent extends CreateRecord
{
    protected static string $resource = MessageParentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $service = App::make(CommunicationParentServiceContract::class);

        if ($data['type'] === 'collectif') {
            return $service->envoyerMessageCollectif(
                $data['cible_type'] ?? 'tous',
                (int) ($data['cible_id'] ?? 0),
                $data['sujet'],
                $data['contenu']
            );
        }

        return $service->envoyerMessageIndividuel(
            (int) ($data['parent_id'] ?? 1),
            $data['sujet'],
            $data['contenu']
        );
    }
}
