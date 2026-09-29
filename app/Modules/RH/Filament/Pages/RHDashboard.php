<?php

declare(strict_types=1);

namespace App\Modules\RH\Filament\Pages;

use App\Modules\RH\Filament\Widgets\RHOverview;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;

class RHDashboard extends Dashboard
{
    protected static ?string $slug = 'rh';

    protected static string $routePath = '/rh';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Ressources humaines';

    protected static ?string $navigationLabel = 'Tableau de bord';

    public function getHeading(): string|Htmlable
    {
        return 'Ressources humaines';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Vue d’ensemble du personnel, des contrats, congés et activités RH.';
    }

    public function getWidgets(): array
    {
        return [RHOverview::class];
    }
}
