<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

class CalendarSettings extends AdministrationSettings
{
    protected static ?string $slug = 'administration/parametres/calendrier';

    protected static ?string $title = 'Calendrier';

    protected const CATEGORY = 'calendrier';
}
