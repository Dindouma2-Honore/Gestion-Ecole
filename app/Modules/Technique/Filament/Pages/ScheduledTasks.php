<?php

declare(strict_types=1);

namespace App\Modules\Technique\Filament\Pages;

class ScheduledTasks extends TechnicalPage
{
    protected static ?string $title = 'Tâches automatiques';

    protected static ?string $navigationLabel = 'Tâches automatiques';

    protected static ?string $slug = 'technique/taches';

    protected static ?int $navigationSort = 4;

    public function notice(): string
    {
        return 'Le signal scheduler prouve le passage du planificateur. Le signal worker concerne uniquement la file technique configurée ; il ne certifie pas les autres traitements.';
    }
}
