<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

class AcademicYearSettings extends AdministrationSettings
{
    protected static ?string $slug = 'administration/parametres/annees';

    protected static ?string $title = 'Années scolaires & périodes';

    protected const CATEGORY = 'annees';
}
