<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Filament\Pages;

class RapportsAssiduite extends AssiduiteSection
{
    protected static ?string $slug = 'assiduite/rapports';

    protected static ?string $title = 'Rapports d’assiduité';

    public function getSectionDescription(): string
    {
        return 'Analysez la présence des élèves, classes et personnels par période.';
    }

    public function getSectionLinks(): array
    {
        return [];
    }
}
