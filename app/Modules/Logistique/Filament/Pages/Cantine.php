<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Pages;

use App\Modules\Logistique\Filament\Resources\AbonnementCantineResource;

class Cantine extends LogistiqueSection
{
    protected static ?string $slug = 'logistique/cantine';

    protected static ?string $title = 'Cantine';

    public function getSectionDescription(): string
    {
        return 'Gérez les abonnements, les menus et les présences au service du jour.';
    }

    public function getSectionLinks(): array
    {
        return [['label' => 'Abonnements', 'description' => 'Élèves inscrits et périodes couvertes', 'icon' => 'heroicon-o-identification', 'url' => AbonnementCantineResource::getUrl()]];
    }
}
