<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Filament\Widgets;

use App\Filament\Support\ModuleAccess;
use App\Modules\Scolarite\Filament\Resources\ClasseResource;
use App\Modules\Scolarite\Filament\Resources\EleveResource;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Scolarite\Filament\Resources\ParentTuteurResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ScolariteOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $stats = [];
        foreach ([
            [EleveResource::class, 'Dossiers élèves', 'heroicon-o-user-group'],
            [ParentTuteurResource::class, 'Parents et tuteurs', 'heroicon-o-users'],
            [ClasseResource::class, 'Classes', 'heroicon-o-building-library'],
            [InscriptionResource::class, 'Inscriptions', 'heroicon-o-document-check'],
        ] as [$resource, $label, $icon]) {
            if ($resource::canViewAny() && ModuleAccess::canAccessComponent(auth()->user(), $resource)) {
                $stats[] = Stat::make(__($label), $resource::getEloquentQuery()->count())->icon($icon)->url($resource::getUrl());
            }
        }

        return $stats;
    }
}
