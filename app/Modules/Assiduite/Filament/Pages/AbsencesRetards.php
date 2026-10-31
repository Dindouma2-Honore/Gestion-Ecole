<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Filament\Pages;

use App\Modules\Assiduite\Filament\Resources\AssiduiteJournaliereResource;

class AbsencesRetards extends AssiduiteSection
{
    protected static ?string $slug = 'assiduite/absences-retards';

    protected static ?string $title = 'Absences & retards';

    public function getSectionDescription(): string
    {
        return 'Contrôlez les absences présumées ou confirmées, retards et départs anticipés.';
    }

    public function getSectionLinks(): array
    {
        return [['label' => 'Registre des statuts', 'description' => 'Filtres par date, personne et statut', 'icon' => 'heroicon-o-clock', 'url' => AssiduiteJournaliereResource::getUrl()]];
    }
}
