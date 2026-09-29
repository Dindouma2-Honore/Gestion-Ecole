<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Resources\PaiementResource\Pages;

use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Filament\Resources\PaiementResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePaiement extends CreateRecord
{
    protected static string $resource = PaiementResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(PaiementServiceContract::class)->enregistrerPaiement(
            (int) $data['eleve_id'],
            (float) $data['montant'],
            $data['mode'],
            $data['reference_mobile_money'] ?? null,
        );
    }
}
