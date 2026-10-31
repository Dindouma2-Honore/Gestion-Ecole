<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Pages;

use App\Modules\Logistique\Filament\Resources\LivreResource;

class Bibliotheque extends LogistiqueSection
{
    protected static ?string $slug = 'logistique/bibliotheque';

    protected static ?string $title = 'Bibliothèque';

    public function getSectionDescription(): string
    {
        return 'Suivez le catalogue, les exemplaires, les emprunts, retours et réservations.';
    }

    public function getSectionLinks(): array
    {
        return [['label' => 'Catalogue', 'description' => 'Ouvrages et exemplaires physiques disponibles', 'icon' => 'heroicon-o-book-open', 'url' => LivreResource::getUrl()]];
    }
}
