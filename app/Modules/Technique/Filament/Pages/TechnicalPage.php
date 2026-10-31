<?php

declare(strict_types=1);

namespace App\Modules\Technique\Filament\Pages;

use App\Modules\Technique\Contracts\MonitoringServiceContract;
use App\Modules\Technique\Services\TechnicalAccess;
use Filament\Pages\Page;

abstract class TechnicalPage extends Page
{
    protected string $view = 'technique::page';

    protected static string|\UnitEnum|null $navigationGroup = 'Technique';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    public static function canAccess(): bool
    {
        return TechnicalAccess::allows(auth()->user());
    }

    public function measurements(): array
    {
        abort_unless(static::canAccess(), 403);

        return app(MonitoringServiceContract::class)->snapshot();
    }

    public function notice(): string
    {
        return 'Les mesures expirées ou absentes sont inconnues. Une connexion disponible ne prouve ni la réplication ni la restaurabilité des données.';
    }
}
