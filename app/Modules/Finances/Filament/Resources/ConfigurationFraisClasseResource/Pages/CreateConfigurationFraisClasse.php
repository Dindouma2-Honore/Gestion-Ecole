<?php

namespace App\Modules\Finances\Filament\Resources\ConfigurationFraisClasseResource\Pages;

use App\Modules\Finances\Exceptions\RepartitionTranchesInvalideException;
use App\Modules\Finances\Filament\Resources\ConfigurationFraisClasseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateConfigurationFraisClasse extends CreateRecord
{
    protected static string $resource = ConfigurationFraisClasseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->verifier($data);

        return $data;
    }

    private function verifier(array $data): void
    {
        $somme = array_sum(array_column($data['tranches'] ?? [], 'montant'));
        if (abs((float) $data['montant_total'] - $somme) > .001) {
            throw ValidationException::withMessages(['data.tranches' => (new RepartitionTranchesInvalideException((float) $data['montant_total'], (float) $somme))->getMessage()]);
        }
    }
}
