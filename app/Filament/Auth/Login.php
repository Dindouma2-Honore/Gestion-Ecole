<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    protected string $view = 'filament.auth.login';

    protected array $extraBodyAttributes = ['class' => 'amb-auth-body'];

    public function hasLogo(): bool
    {
        return false;
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Connectez-vous à votre compte';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Accédez à votre espace de gestion scolaire sécurisé.';
    }
}
