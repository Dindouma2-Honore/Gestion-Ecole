<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources\VisiteInfirmerieResource\Pages;

use App\Modules\VieScolaire\Filament\Resources\VisiteInfirmerieResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateVisiteInfirmerie extends CreateRecord
{
    protected static string $resource = VisiteInfirmerieResource::class;
     protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['date_heure'] = now();
        $data['traite_par'] = Auth::id();

        return $data;
    }
}
