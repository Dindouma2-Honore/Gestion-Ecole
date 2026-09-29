<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources\VisiteurResource\Pages;

use App\Modules\VieScolaire\Filament\Resources\VisiteurResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateVisiteur extends CreateRecord
{
    protected static string $resource = VisiteurResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['enregistre_par'] = Auth::id();

        return $data;
    }
}
