<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    protected static ?string $title = 'Mon profil';

    public function getTitle(): string
    {
        return __('interface.profile');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('photo_profil_path')
                ->label('Photo de profil')
                ->avatar()
                ->image()
                ->disk('public')
                ->directory('avatars'),
            $this->getNameFormComponent()->label('Nom d’affichage'),
            TextInput::make('telephone')
                ->label('Téléphone')
                ->tel()
                ->maxLength(20),
            $this->getEmailFormComponent(),
            Select::make('locale')
                ->label(__('interface.language'))
                ->options(['fr' => 'Français', 'en' => 'English'])
                ->required()
                ->native(false),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }
}
