<?php

declare(strict_types=1);

namespace App\Modules\Technique\Filament\Pages;

class Integrations extends TechnicalPage
{
    protected static ?string $title = 'Intégrations & services';

    protected static ?string $navigationLabel = 'Intégrations & services';

    protected static ?string $slug = 'technique/integrations';

    protected static ?int $navigationSort = 5;

    public function notice(): string
    {
        return 'Aucun test de disponibilité des intégrations n’est activé. Aucun message ni paiement n’est envoyé.';
    }
}
