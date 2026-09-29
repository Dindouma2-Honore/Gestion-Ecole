<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class ChangerMotDePasse extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';

    protected string $view = 'filament.pages.changer-mot-de-passe';

    protected static ?string $title = 'Modification obligatoire du mot de passe';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('current_password')
                    ->label('Mot de passe temporaire / actuel')
                    ->password()
                    ->revealable()
                    ->required()
                    ->currentPassword(),

                TextInput::make('new_password')
                    ->label('Nouveau mot de passe')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8)
                    ->same('new_password_confirmation'),

                TextInput::make('new_password_confirmation')
                    ->label('Confirmer le nouveau mot de passe')
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        /** @var User $user */
        $user = auth()->user();

        $user->update([
            'password' => Hash::make($data['new_password']),
            'must_change_password' => false,
        ]);

        Notification::make()
            ->title('Mot de passe mis à jour avec succès')
            ->success()
            ->send();

        $this->redirect('/admin');
    }
}
