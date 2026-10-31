<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Filament\Pages;

use App\Modules\Pedagogie\Services\ProgressionMonitoringService;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class SupervisionProgressionsDirection extends Page
{
    protected static ?string $slug = 'pedagogie/supervision-progressions';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Supervision de la progression pédagogique';

    protected string $view = 'pedagogie::filament.pages.supervision-progressions-direction';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user !== null && ($user->hasRole('Fondateur') || $user->can('pedagogie.progression.superviser'));
    }

    public function getSupervisionData(): array
    {
        $anneeId = app(AnneeScolaireServiceContract::class)->getAnneeCouranteId();

        /** @var ProgressionMonitoringService $monitoring */
        $monitoring = app(ProgressionMonitoringService::class);

        return $monitoring->getSupervisionDirection($anneeId);
    }
}
