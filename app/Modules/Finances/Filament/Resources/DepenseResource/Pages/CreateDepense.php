<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\DepenseResource\Pages;

use App\Modules\Finances\Contracts\GestionDepenseServiceContract;
use App\Modules\Finances\Filament\Resources\DepenseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDepense extends CreateRecord
{
    protected static string $resource = DepenseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Model */
        return app(GestionDepenseServiceContract::class)->creerDepense(
            (int) $data['rubrique_depense_id'],
            $data['libelle'],
            (float) $data['montant'],
            $data['motif'],
            $data['justificatif'] ?? null,
        );
    }
}
