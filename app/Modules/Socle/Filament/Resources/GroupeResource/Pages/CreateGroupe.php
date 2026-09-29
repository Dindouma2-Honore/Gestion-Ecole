<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\GroupeResource\Pages;

use App\Modules\Socle\Contracts\GroupeServiceContract;
use App\Modules\Socle\Filament\Resources\GroupeResource;
use App\Modules\Socle\Models\Groupe;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateGroupe extends CreateRecord
{
    protected static string $resource = GroupeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Groupe $groupe */
        $groupe = app(GroupeServiceContract::class)->creer(Arr::only($data, ['nom', 'description']));

        return $groupe;
    }
}
