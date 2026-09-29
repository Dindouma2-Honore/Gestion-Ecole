<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\ClasseResource\Pages;

use App\Modules\Scolarite\Filament\Resources\ClasseResource;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Services\ClasseConfigurationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateClasse extends CreateRecord
{
    protected static string $resource = ClasseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Classe {
            $data['frais'] = (float) config('scolarite.frais_inscription', 15_000);
            $classe = Classe::create($data);
            app(ClasseConfigurationService::class)->enregistrer($classe, $this->data);

            return $classe;
        });
    }
}
