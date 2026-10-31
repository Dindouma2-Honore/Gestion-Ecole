<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Pages;

use Filament\Pages\Page;

abstract class PedagogieSection extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'pedagogie::filament.pages.section';

    /** @return array<int, array{label:string, description:string, icon:string, url:string}> */
    abstract public function getSectionLinks(): array;

    public function getSectionDescription(): string
    {
        return '';
    }
}
