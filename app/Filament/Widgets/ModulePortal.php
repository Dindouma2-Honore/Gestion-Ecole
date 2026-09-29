<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Support\ModuleCatalog;
use Filament\Widgets\Widget;

class ModulePortal extends Widget
{
    protected string $view = 'filament.widgets.module-portal';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getViewData(): array
    {
        return [
            'modules' => ModuleCatalog::visibleFor(auth()->user()),
        ];
    }
}
