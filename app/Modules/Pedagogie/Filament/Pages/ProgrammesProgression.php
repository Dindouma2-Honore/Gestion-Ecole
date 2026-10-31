<?php

namespace App\Modules\Pedagogie\Filament\Pages;

use App\Modules\Pedagogie\Filament\Resources\ProgrammeResource;
use App\Modules\Pedagogie\Filament\Resources\ProgressionResource;
use App\Modules\Pedagogie\Filament\Resources\SuiviProgressionResource;

class ProgrammesProgression extends PedagogieSection
{
    protected static ?string $slug = 'pedagogie/programmes-progression';

    protected static ?string $title = 'Programmes & progression';

    public function getSectionDescription(): string
    {
        return 'Comparez ce qui devait être enseigné avec les contenus réellement traités.';
    }

    public function getSectionLinks(): array
    {
        return [
            ['label' => 'Mes programmes', 'description' => 'Import PDF / Word, analyse, validation et versions', 'icon' => 'heroicon-o-clipboard-document-list', 'url' => ProgrammeResource::getUrl()],
            ['label' => 'Progression des séances', 'description' => 'Contenus issus des séances dispensées', 'icon' => 'heroicon-o-chart-bar-square', 'url' => ProgressionResource::getUrl()],
            ['label' => 'Suivi des progressions', 'description' => 'Programme réalisé, année écoulée, écarts et alertes', 'icon' => 'heroicon-o-chart-bar', 'url' => SuiviProgressionResource::getUrl()],
        ];
    }
}
