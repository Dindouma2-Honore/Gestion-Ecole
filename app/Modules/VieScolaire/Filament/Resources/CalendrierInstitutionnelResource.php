<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Filament\Resources;

use App\Modules\Socle\Filament\Resources\JourFerieResource;
use App\Modules\VieScolaire\Filament\Resources\CalendrierInstitutionnelResource\Pages;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CalendrierInstitutionnelResource extends JourFerieResource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'exception de calendrier';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('libelle')->label('Titre')->required(), Select::make('type')->options(['ferie_national' => 'Jour férié national', 'ferie_interne' => 'Jour férié interne', 'vacances' => 'Vacances', 'journee_pedagogique' => 'Journée pédagogique', 'fermeture_exceptionnelle' => 'Fermeture exceptionnelle', 'sans_cours' => 'Journée sans cours', 'autre' => 'Autre'])->required(), DatePicker::make('date_debut')->label('Début')->required(), DatePicker::make('date_fin')->label('Fin')->required(), Textarea::make('description')->columnSpanFull(), Toggle::make('affecte_cours')->label('Impacte les cours')->default(true), Toggle::make('affecte_personnel')->label('Impacte le personnel')->default(true), Toggle::make('etablissement_ferme')->label('Établissement fermé')->default(true), Toggle::make('notifier')->label('Notifier les utilisateurs'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCalendrier::route('/'), 'create' => Pages\CreateCalendrier::route('/create'), 'edit' => Pages\EditCalendrier::route('/{record}/edit')];
    }
}
