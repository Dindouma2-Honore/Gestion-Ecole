<?php

namespace App\Modules\Pedagogie\Filament\Pages;

use App\Modules\Pedagogie\Filament\Resources\BulletinResource;
use App\Modules\Pedagogie\Filament\Resources\ConseilClasseResource;

class BulletinsConseils extends PedagogieSection
{
    protected static ?string $slug = 'pedagogie/bulletins-conseils';

    protected static ?string $title = 'Résultats & bulletins';

    public function getSectionDescription(): string
    {
        return 'Clôturez une période, validez les décisions et publiez les bulletins.';
    }

    public function getSectionLinks(): array
    {
        return [
            ['label' => 'Bulletins & publication', 'description' => 'Calcul, validation, PDF et archivage', 'icon' => 'heroicon-o-document-chart-bar', 'url' => BulletinResource::getUrl()],
            ['label' => 'Conseils de classe', 'description' => 'Synthèse et décisions collectives', 'icon' => 'heroicon-o-user-group', 'url' => ConseilClasseResource::getUrl()],
        ];
    }
}
