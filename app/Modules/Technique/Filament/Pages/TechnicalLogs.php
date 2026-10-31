<?php

declare(strict_types=1);

namespace App\Modules\Technique\Filament\Pages;

class TechnicalLogs extends TechnicalPage
{
    protected static ?string $title = 'Logs & supervision';

    protected static ?string $navigationLabel = 'Logs & supervision';

    protected static ?string $slug = 'technique/logs';

    protected static ?int $navigationSort = 6;

    public function notice(): string
    {
        return 'La collecte et le regroupement des erreurs ne sont pas encore activés. Les logs bruts ne sont pas exposés.';
    }
}
