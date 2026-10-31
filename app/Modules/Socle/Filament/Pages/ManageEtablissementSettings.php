<?php

declare(strict_types=1);

namespace App\Modules\Socle\Filament\Pages;

use App\Models\User;
use App\Modules\Socle\Models\ConfigEtablissement;
use App\Modules\Socle\Settings\SystemSettings;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class ManageEtablissementSettings extends SettingsPage
{
    protected static string $settings = SystemSettings::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Établissement & paramètres';

    protected static ?string $navigationLabel = 'Établissement';

    protected static ?string $title = 'Établissement';

    protected const FIELDS = ['nom', 'sigle', 'logo_path', 'adresse', 'telephone', 'email', 'site_web', 'rccm', 'niu', 'devise'];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole('Fondateur') === true;
    }

    public function mount(): void
    {
        $this->form->fill(ConfigEtablissement::get()->only(static::FIELDS));
    }

    public function save(): void
    {
        ConfigEtablissement::get()->update(Arr::only($this->form->getState(), static::FIELDS));

        Notification::make()->success()->title('Configuration enregistrée')->send();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom')->label('Nom officiel')->required()->maxLength(255),
            TextInput::make('sigle')->label('Sigle')->maxLength(50),
            FileUpload::make('logo_path')->label('Logo')->image()->directory('etablissement')->columnSpanFull(),
            Textarea::make('adresse')->columnSpanFull(),
            TextInput::make('telephone')->tel()->maxLength(50),
            TextInput::make('email')->email()->maxLength(255),
            TextInput::make('site_web')->label('Site web')->url()->maxLength(255),
            TextInput::make('devise')->label('Devise')->default('XAF')->required()->maxLength(10),
            TextInput::make('rccm')->label('RCCM')->maxLength(255),
            TextInput::make('niu')->label('NIU')->maxLength(255),
        ])->columns(2);
    }
}
