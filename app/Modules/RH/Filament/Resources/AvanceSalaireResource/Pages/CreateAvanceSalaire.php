<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Resources\AvanceSalaireResource\Pages;

use App\Modules\RH\Contracts\PaieServiceContract;
use App\Modules\RH\Filament\Resources\AvanceSalaireResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAvanceSalaire extends CreateRecord
{
    protected static string $resource = AvanceSalaireResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Model */
        return app(PaieServiceContract::class)->demanderAvance(
            (int) $data['employe_id'], (float) $data['montant'], $data['motif'],
            (bool) ($data['derogation_plafond'] ?? false), $data['motif_derogation'] ?? null,
        );
    }
}
