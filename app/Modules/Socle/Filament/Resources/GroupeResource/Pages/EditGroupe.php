<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources\GroupeResource\Pages;

use App\Modules\Socle\Contracts\GroupeServiceContract;
use App\Modules\Socle\Filament\Resources\GroupeResource;
use App\Modules\Socle\Models\Groupe;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditGroupe extends EditRecord
{
    protected static string $resource = GroupeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Groupe $record */
        return app(GroupeServiceContract::class)->modifier($record, Arr::only($data, ['nom', 'description']));
    }
}
