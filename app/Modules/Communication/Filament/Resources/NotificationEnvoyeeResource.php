<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources;

use App\Modules\Communication\Filament\Resources\NotificationEnvoyeeResource\Pages;
use App\Modules\Communication\Models\NotificationEnvoyee;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class NotificationEnvoyeeResource extends Resource
{
    protected static ?string $model = NotificationEnvoyee::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell';

    protected static string|UnitEnum|null $navigationGroup = 'Communication';

    protected static ?string $navigationLabel = 'Historique Notifications';

    protected static ?string $modelLabel = 'notification';

    protected static ?string $pluralModelLabel = 'notifications envoyées';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('canal')
                    ->label('Canal')
                    ->options([
                        'whatsapp' => 'WhatsApp',
                        'sms' => 'SMS',
                        'email' => 'Email',
                        'in_app' => 'In-App',
                    ]),

                TextInput::make('code_template')
                    ->label('Code Template'),

                TextInput::make('destinataire_type')
                    ->label('Type Destinataire'),

                TextInput::make('destinataire_contact')
                    ->label('Contact Destinataire'),

                Select::make('statut')
                    ->label('Statut')
                    ->options([
                        'en_attente' => 'En attente',
                        'envoyee' => 'Envoyée',
                        'lue' => 'Lue',
                        'echec' => 'Échec',
                    ]),

                TextInput::make('tentatives')
                    ->label('Nombre de tentatives')
                    ->numeric(),

                Toggle::make('accuse_reception')
                    ->label('Accusé de réception'),

                DateTimePicker::make('envoyee_le')
                    ->label('Envoyée le'),

                Textarea::make('contenu_final')
                    ->label('Contenu du message')
                    ->rows(6),

                Textarea::make('erreur_message')
                    ->label('Message d\'erreur (si échec)')
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('canal')->badge()->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code_template')->label('Code Template')->searchable(),
                Tables\Columns\TextColumn::make('destinataire_contact')->label('Contact')->searchable(),
                Tables\Columns\TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'envoyee', 'lue' => 'success',
                        'en_attente' => 'warning',
                        'echec' => 'danger',
                        default => 'secondary',
                    }),
                Tables\Columns\TextColumn::make('tentatives')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Créé le')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('canal')
                    ->options([
                        'whatsapp' => 'WhatsApp',
                        'sms' => 'SMS',
                        'email' => 'Email',
                        'in_app' => 'In-App',
                    ]),
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'en_attente' => 'En attente',
                        'envoyee' => 'Envoyée',
                        'lue' => 'Lue',
                        'echec' => 'Échec',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationEnvoyees::route('/'),
            'view' => Pages\ViewNotificationEnvoyee::route('/{record}'),
        ];
    }
}
