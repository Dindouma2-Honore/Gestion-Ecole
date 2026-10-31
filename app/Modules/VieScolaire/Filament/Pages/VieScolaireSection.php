<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Pages;

use Filament\Pages\Page;

abstract class VieScolaireSection extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'vie-scolaire::filament.pages.section';

    abstract public function getSectionLinks(): array;

    public function getSectionDescription(): string
    {
        return '';
    }
}
