<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ManagePedagogicalRules extends ManageEtablissementSettings
{
    protected static ?string $slug = 'administration/parametres/regles-pedagogiques';

    protected static ?string $title = 'Règles pédagogiques';

    protected static bool $shouldRegisterNavigation = false;

    protected const FIELDS = ['systeme_notation', 'note_maximale', 'precision_arrondi', 'regles_evaluation'];

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('systeme_notation')->label('Système de notation')->options(['numerique' => 'Notation numérique', 'lettres' => 'Notation par lettres', 'competences' => 'Évaluation par compétences'])->default('numerique')->required(),
            TextInput::make('note_maximale')->label('Note maximale')->numeric()->minValue(1)->default(20)->required(),
            Select::make('precision_arrondi')->label('Précision des arrondis')->options([0 => 'Nombre entier', 1 => '1 décimale', 2 => '2 décimales'])->default(2)->required(),
            Textarea::make('regles_evaluation')->label('Règles générales d’évaluation')->rows(5)->columnSpanFull(),
        ])->columns(2);
    }
}
