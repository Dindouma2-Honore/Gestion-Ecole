<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages;

use App\Modules\Scolarite\Filament\Resources\EleveResource;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use BackedEnum;
use Filament\Resources\Pages\Page;
use UnitEnum;

class ListEleves extends Page
{
    protected static string $resource = InscriptionResource::class;

    protected static ?string $slug = 'eleves';

    protected static ?string $navigationLabel = 'Élèves';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static UnitEnum|string|null $navigationGroup = 'Scolarité';

    public function mount(): \Illuminate\Http\RedirectResponse
    {
        return redirect(EleveResource::getUrl('index'));
    }
}
