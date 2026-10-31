<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Filament\Pages;

use App\Modules\Assiduite\Filament\Resources\AssiduiteJournaliereResource;
use App\Modules\Assiduite\Filament\Resources\PointageBrutResource;

class PointagePersonnel extends AssiduiteSection
{
    protected static ?string $slug = 'assiduite/pointage-personnel';

    protected static ?string $title = 'Pointage du personnel';

    public function getSectionDescription(): string
    {
        return 'Consolidez les arrivées, départs et données biométriques du personnel.';
    }

    public function getSectionLinks(): array
    {
        return [
            ['label' => 'Assiduité du personnel', 'description' => 'Horaires, retard, départ et temps de présence', 'icon' => 'heroicon-o-user-group', 'url' => AssiduiteJournaliereResource::getUrl('index', ['tableFilters' => ['personne_type' => ['value' => 'personnel']]])],
            ['label' => 'Pointages biométriques bruts', 'description' => 'Imports immuables et contrôle des doublons', 'icon' => 'heroicon-o-finger-print', 'url' => PointageBrutResource::getUrl()],
        ];
    }
}
