<?php

declare(strict_types=1);

namespace App\Support;

final class ModuleVisuals
{
    public static function icon(string $category): string
    {
        return match ($category) {
            'Administration' => 'heroicon-o-shield-check',
            'RH' => 'heroicon-o-user-group',
            'Scolarité' => 'heroicon-o-academic-cap',
            'Pédagogie' => 'heroicon-o-book-open',
            'Communication' => 'heroicon-o-chat-bubble-left-right',
            'Finances' => 'heroicon-o-banknotes',
            'Vie scolaire' => 'heroicon-o-clock',
            'Logistique' => 'heroicon-o-building-office-2',
            'Rapports' => 'heroicon-o-chart-bar',
            default => 'heroicon-o-squares-2x2',
        };
    }

    private function __construct() {}
}
