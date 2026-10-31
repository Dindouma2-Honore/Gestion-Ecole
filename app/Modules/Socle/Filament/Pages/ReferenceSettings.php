<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

class ReferenceSettings extends AdministrationSettings
{
    protected static ?string $slug = 'administration/parametres/referentiels';

    protected static ?string $title = 'Référentiels généraux';

    protected const CATEGORY = 'referentiels';
}
