<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Schema;

class ManageWorkingDays extends ManageEtablissementSettings
{
    protected static ?string $slug = 'administration/parametres/jours-ouvrables';

    protected static ?string $title = 'Jours ouvrables';

    protected static bool $shouldRegisterNavigation = false;

    protected const FIELDS = ['jours_ouvrables'];

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            CheckboxList::make('jours_ouvrables')->label('Jours ouvrables')->options([
                1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi',
                5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche',
            ])->default([1, 2, 3, 4, 5, 6])->columns(4)->columnSpanFull(),
        ])->columns(2);
    }
}
