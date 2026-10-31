<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources;

use App\Modules\Logistique\Filament\Resources\EvenementResource;
use App\Modules\VieScolaire\Filament\Resources\EvenementScolaireResource\Pages;

class EvenementScolaireResource extends EvenementResource
{
    protected static bool $shouldRegisterNavigation = false;

    public static function getPages(): array
    {
        return ['index' => Pages\ListEvenements::route('/'), 'create' => Pages\CreateEvenement::route('/create'), 'edit' => Pages\EditEvenement::route('/{record}/edit')];
    }
}
