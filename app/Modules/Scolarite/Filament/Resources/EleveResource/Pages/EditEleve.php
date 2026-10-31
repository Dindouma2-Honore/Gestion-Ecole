<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\EleveResource\Pages;

use App\Modules\Finances\Contracts\FacturePreinscriptionServiceContract;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Filament\Resources\EleveResource;
use Filament\Resources\Pages\EditRecord;

class EditEleve extends EditRecord
{
    protected static string $resource = EleveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EleveResource::inscrireTableAction(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $parent = $this->record->parentsTuteurs()
            ->wherePivot('responsable_paiement', true)
            ->first()
            ?? $this->record->parentsTuteurs()->first();

        if ($parent) {
            $data['nom_tuteur'] = filled($data['nom_tuteur'] ?? null) ? $data['nom_tuteur'] : trim("{$parent->nom} {$parent->prenom}");
            $data['telephone_tuteur'] = filled($data['telephone_tuteur'] ?? null) ? $data['telephone_tuteur'] : $parent->telephone;
        }

        return $data;
    }
}
