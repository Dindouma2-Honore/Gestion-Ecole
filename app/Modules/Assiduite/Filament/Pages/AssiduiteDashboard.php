<?php

namespace App\Modules\Assiduite\Filament\Pages;

use Filament\Pages\Page;

class AssiduiteDashboard extends Page
{
    protected static ?string $slug = 'assiduite';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Assiduité';

    protected static ?string $navigationLabel = 'Tableau de bord';

    protected static ?string $title = 'Assiduité';

    protected string $view = 'filament.pages.module-placeholder';

    public function getModuleIcon(): string
    {
        return 'heroicon-o-clock';
    }

    public function getModuleDescription(): string
    {
        return 'Le tableau de bord Assiduité accueillera les indicateurs des présences, retards, absences et pointages.';
    }
}
