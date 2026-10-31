<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

use App\Models\User;
use App\Modules\Socle\Settings\SystemSettings;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ManageSystemSettings extends SettingsPage
{
    protected static string $settings = SystemSettings::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration système';

    protected static ?string $navigationLabel = 'Paramètres système';

    protected static ?string $title = 'Paramètres système';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasAnyRole(['Fondateur', 'Super Admin', 'Administrateur', 'Admin']) === true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Configuration technique')->tabs([
                Tab::make('Notifications')->icon('heroicon-o-bell')->schema([
                    Section::make('Canaux disponibles')->description('Les secrets de connexion restent protégés dans l’environnement du serveur.')->schema([
                        Text::make('SMS — '.(filled(config('services.sms.key')) ? '● Configuré' : '○ Non configuré')),
                        Text::make('WhatsApp — '.(filled(config('services.whatsapp.token')) ? '● Configuré' : '○ Non configuré')),
                        Text::make('E-mail — '.(filled(config('mail.mailers.smtp.host')) ? '● Configuré' : '○ Non configuré')),
                        Text::make('Notifications internes — Non vérifié'),
                    ])->columns(2),
                    TextInput::make('delaiPremiereRelanceHeures')
                        ->label('Première relance (heures)')->numeric()->minValue(1)->required(),
                    TextInput::make('delaiDeuxiemeRelanceHeures')
                        ->label('Deuxième relance (heures)')->numeric()->minValue(1)->required(),
                ]),
                Tab::make('Intégrations')->icon('heroicon-o-puzzle-piece')->schema([
                    Section::make('État des connexions')->schema([
                        Text::make('Mobile Money — '.(filled(config('services.mobile_money.key')) ? '● Configuré' : '○ Non configuré')),
                        Text::make('Biométrie — '.(filled(config('services.biometrie.url')) ? '● Configuré' : '○ Non configuré')),
                        Text::make('Plateforme ministérielle — '.(filled(config('services.ministere.url')) ? '● Configuré' : '○ Non configuré')),
                        Text::make('Banque / comptabilité — '.(filled(config('services.banque.url')) ? '● Configuré' : '○ Non configuré')),
                    ])->columns(2),
                ]),
                Tab::make('Sauvegardes')->icon('heroicon-o-circle-stack')->schema([
                    Section::make('Supervision des sauvegardes')->description('Les sauvegardes et restaurations de la base sont gérées dans le module Technique. Les documents stockés séparément ne sont pas inclus.')->schema([
                        Text::make('Base de données — Non vérifié'),
                        Text::make('Documents — Non vérifié'),
                        Text::make('Alertes d’échec — Non vérifié'),
                    ])->columns(3),
                ]),
                Tab::make('Tâches automatiques')->icon('heroicon-o-arrow-path')->schema([
                    Section::make('État des traitements')->schema([
                        Text::make('Notifications — Non vérifié'),
                        Text::make('Détection des absences — Non vérifié'),
                        Text::make('Relances automatiques — Non vérifié'),
                        Text::make('Sauvegardes — Non vérifié'),
                        Text::make('Synchronisation — Non vérifiée'),
                    ])->columns(2),
                    TextInput::make('delaiToleranceMinutes')
                        ->label('Tolérance de retard (minutes)')
                        ->numeric()->minValue(0)->required(),
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
                ])->columns(2),
            ])->columnSpanFull()->persistTabInQueryString(),
        ]);
    }
}
