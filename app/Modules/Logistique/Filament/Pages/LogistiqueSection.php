<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Filament\Pages;

use Filament\Pages\Page;

abstract class LogistiqueSection extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'logistique::filament.pages.section';

    /** @return array<int, array{label:string, description:string, icon:string, url:string}> */
    abstract public function getSectionLinks(): array;

    public function getSectionDescription(): string
    {
        return '';
    }
}
