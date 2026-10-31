<?php

declare(strict_types=1);

namespace App\Modules\Technique\Filament\Pages;

class TechnicalSecurity extends TechnicalPage
{
    protected static ?string $title = 'Sécurité technique';

    protected static ?string $navigationLabel = 'Sécurité technique';

    protected static ?string $slug = 'technique/securite';

    protected static ?int $navigationSort = 7;

    public function notice(): string
    {
        return 'Accès soumis à une permission technique et aux habilitations du module. Aucun contrôle de certificat ou de sauvegarde chiffrée n’est encore activé.';
    }
}
