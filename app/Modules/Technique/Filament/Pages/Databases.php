<?php

declare(strict_types=1);

namespace App\Modules\Technique\Filament\Pages;

class Databases extends TechnicalPage
{
    protected static ?string $title = 'Bases de données & réplication';

    protected static ?string $navigationLabel = 'Bases de données & réplication';

    protected static ?string $slug = 'technique/bases';

    protected static ?int $navigationSort = 2;

    public function notice(): string
    {
        return 'Seule la connexion principale est mesurée. Aucun fournisseur de réplication n’est activé.';
    }
}
