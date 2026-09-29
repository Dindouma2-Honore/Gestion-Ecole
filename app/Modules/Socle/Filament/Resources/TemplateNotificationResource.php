<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Resources;

use App\Modules\Socle\Models\TemplateNotification;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TemplateNotificationResource extends Resource
{
    protected static ?string $model = TemplateNotification::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell';

    protected static string|\UnitEnum|null $navigationGroup = 'Workflows & validations';

    protected static ?string $modelLabel = 'Modèle de notification';

    protected static ?string $pluralModelLabel = 'Modèles de notifications';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(50),
            Select::make('canal')->options(['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'E-mail', 'in_app' => 'Dans l’application'])->required(),
            TextInput::make('sujet')->maxLength(255),
            Textarea::make('contenu')->helperText('Placeholders : {nom_eleve}, {date}, …')->rows(8)->required()->columnSpanFull(),
            Toggle::make('actif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->searchable()->sortable(), TextColumn::make('canal')->badge()->sortable(),
            TextColumn::make('sujet')->limit(40), IconColumn::make('actif')->boolean(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => TemplateNotificationResource\Pages\ListTemplatesNotifications::route('/'), 'create' => TemplateNotificationResource\Pages\CreateTemplateNotification::route('/create'), 'edit' => TemplateNotificationResource\Pages\EditTemplateNotification::route('/{record}/edit')];
    }
}
