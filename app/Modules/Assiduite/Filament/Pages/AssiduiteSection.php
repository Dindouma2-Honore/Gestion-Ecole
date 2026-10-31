<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Filament\Pages;

use Filament\Pages\Page;

abstract class AssiduiteSection extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'assiduite::filament.pages.section';

    abstract public function getSectionLinks(): array;

    public function getSectionDescription(): string
    {
        return '';
    }
}
