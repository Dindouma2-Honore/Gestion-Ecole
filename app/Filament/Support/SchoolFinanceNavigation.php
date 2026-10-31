<?php

namespace App\Filament\Support;

use App\Modules\Finances\Filament\Pages\SituationFinanciere;
use App\Modules\Finances\Filament\Resources\ConfigurationFraisClasseResource;
use App\Modules\Finances\Filament\Resources\FacturePreinscriptionResource;
use App\Modules\Finances\Filament\Resources\GrilleFraisResource;
use App\Modules\Finances\Filament\Resources\PaiementResource;
use App\Modules\Finances\Filament\Resources\RelancePaiementResource;
use App\Modules\Finances\Filament\Resources\RemiseExonerationResource;

/** UI ownership; financial services and their authorization remain in their module. */
final class SchoolFinanceNavigation
{
    public const COMPONENTS = [
        PaiementResource::class => 'Paiements scolaires',
        GrilleFraisResource::class => 'Grilles de frais scolaires',
        RemiseExonerationResource::class => 'Remises & exonérations',
        SituationFinanciere::class => 'Statut des paiements',
    ];

    public static function contains(string $component): bool
    {
        foreach (array_keys(self::COMPONENTS) as $class) {
            if ($component === $class || str_starts_with($component, $class.'\\') || str_starts_with($component, $class.'@')) {
                return true;
            }
        }

        return false;
    }
}
