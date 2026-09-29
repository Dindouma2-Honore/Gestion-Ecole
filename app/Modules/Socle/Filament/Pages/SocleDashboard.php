<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

use App\Filament\Widgets\AdministrationOverview;
use App\Modules\Socle\Filament\Resources\UserResource;
use App\Modules\Socle\Filament\Widgets\AnneeScolaireOverview;
use App\Modules\Socle\Filament\Widgets\AuditOverview;
use App\Modules\Socle\Filament\Widgets\CourrierOverview;
use App\Modules\Socle\Filament\Widgets\DocumentOverview;
use App\Modules\Socle\Filament\Widgets\ParametrageOverview;
use App\Modules\Socle\Filament\Widgets\ReunionOverview;
use App\Modules\Socle\Filament\Widgets\TacheOverview;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;

class SocleDashboard extends Dashboard
{
    protected static ?string $slug = 'socle';

    protected static string $routePath = '/socle';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'Tableau de bord';

    public function mount(): void
    {
        $this->redirect(UserResource::getUrl(), navigate: true);
    }

    public function getHeading(): string|Htmlable
    {
        return 'Administration générale';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Socle, paramétrage, années scolaires, audit et organisation administrative.';
    }

    public function getWidgets(): array
    {
        return [AdministrationOverview::class, ParametrageOverview::class, AnneeScolaireOverview::class, AuditOverview::class, DocumentOverview::class, CourrierOverview::class, TacheOverview::class, ReunionOverview::class];
    }

    public function getColumns(): int|array
    {
        return [
            'sm' => 1,
            'md' => 2,
            'xl' => 4,
        ];
    }
}
