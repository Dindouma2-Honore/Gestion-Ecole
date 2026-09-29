<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources\SortieEleveResource\Pages;

use App\Modules\VieScolaire\Filament\Resources\SortieEleveResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSortieEleve extends CreateRecord
{
    protected static string $resource = SortieEleveResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['remis_par'] = Auth::id();

        return $data;
    }
}