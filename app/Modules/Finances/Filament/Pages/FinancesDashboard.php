<?php

declare(strict_types=1);

namespace App\Modules\Finances\Filament\Pages;

use App\Modules\Finances\Filament\Widgets\FinancePilotage;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;

class FinancesDashboard extends Dashboard
{
    public static function getNavigationLabel(): string
    {
        return __('interface.finance_dashboard');
    }

    protected static ?string $slug = 'finances';

    protected static string $routePath = '/finances';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?string $navigationLabel = 'Tableau de bord';

    protected static ?int $navigationSort = 1;

    public function getHeading(): string|Htmlable
    {
        return 'Finances';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Vue d’ensemble des frais scolaires, échéanciers, remises et exonérations.';
    }

    public function getWidgets(): array
    {
        return [FinancePilotage::class];
    }
}
