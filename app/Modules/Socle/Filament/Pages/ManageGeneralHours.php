<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class ManageGeneralHours extends ManageEtablissementSettings
{
    protected static ?string $slug = 'administration/parametres/horaires-generaux';

    protected static ?string $title = 'Horaires généraux';

    protected static bool $shouldRegisterNavigation = false;

    protected const FIELDS = ['heure_ouverture', 'heure_fermeture', 'tolerance_retard_minutes'];

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TimePicker::make('heure_ouverture')->label('Heure d’ouverture')->seconds(false),
            TimePicker::make('heure_fermeture')->label('Heure de fermeture')->seconds(false)->after('heure_ouverture'),
            TextInput::make('tolerance_retard_minutes')->label('Tolérance de retard')->numeric()->minValue(0)->suffix('minutes')->default(10),
        ])->columns(2);
    }
}
