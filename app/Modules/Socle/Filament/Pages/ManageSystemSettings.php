<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

use App\Models\User;
use App\Modules\Socle\Settings\SystemSettings;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ManageSystemSettings extends SettingsPage
{
    protected static string $settings = SystemSettings::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Structure de l’établissement';

    protected static ?string $navigationLabel = 'Paramètres système';

    protected static ?string $title = 'Paramètres système';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('Fondateur') === true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('delaiToleranceMinutes')
                ->label('Tolérance de retard (minutes)')
                ->numeric()->minValue(0)->required(),
            TextInput::make('delaiPremiereRelanceHeures')
                ->label('Première relance (heures)')
                ->numeric()->minValue(1)->required(),
            TextInput::make('delaiDeuxiemeRelanceHeures')
                ->label('Deuxième relance (heures)')
                ->numeric()->minValue(1)->required(),
            TextInput::make('delaiModificationNotesJours')
                ->label('Délai de modification des notes (jours)')
                ->helperText('Après ce délai, seul le Fondateur peut autoriser une correction exceptionnelle.')
                ->numeric()->minValue(0)->required(),
            TextInput::make('tauxCotisationCnpsSalarie')
                ->label('Taux de cotisation CNPS salarié')
                ->numeric()->minValue(0)->maxValue(1)->step(0.001)->required(),
            TextInput::make('joursOuvrablesPaie')
                ->label('Jours ouvrables mensuels pour la paie')
                ->numeric()->minValue(1)->maxValue(31)->required(),
        ]);
    }
}
