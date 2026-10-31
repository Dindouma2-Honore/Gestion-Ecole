<?php

namespace App\Modules\Communication\Services;

use App\Models\User;
use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Communication\Models\CommunicationTemplate;
use App\Modules\RH\Models\Employe;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Password;

class StaffAccountWhatsApp
{
    public function template(): CommunicationTemplate
    {
        return CommunicationTemplate::firstOrCreate([
            'waba_id' => (string) config('whatsapp.waba_id'), 'code' => 'staff_account_access', 'language' => 'fr',
        ], [
            'name' => 'Accès au compte du personnel', 'provider_template_name' => 'staff_account_access',
            'audience' => 'STAFF', 'module' => 'RH', 'trigger_event' => 'staff_account_created',
            'provider_category' => 'UTILITY', 'header_type' => 'NONE',
            'body' => 'Bonjour {{1}}, votre compte Ambassadors Educational Complex est créé. Identifiant : {{2}}. Définissez votre mot de passe avec ce lien personnel : {{3}}. Ce lien est temporaire ; s’il expire, utilisez Mot de passe oublié sur la page de connexion.',
            'variables_schema' => [
                ['component' => 'BODY', 'position' => 1, 'key' => 'recipient_name', 'example' => 'Marie Dupont'],
                ['component' => 'BODY', 'position' => 2, 'key' => 'login', 'example' => 'marie@example.com'],
                ['component' => 'BODY', 'position' => 3, 'key' => 'activation_url', 'example' => 'https://example.com/activation/exemple'],
            ],
            'buttons' => [], 'local_status' => 'DRAFT', 'meta_status' => 'NOT_SUBMITTED',
        ]);
    }

    public function dispatch(User $user): CommunicationMessage
    {
        $this->template();

        return app(WhatsAppDispatcher::class)->dispatch((string) $user->telephone, [
            'type' => 'staff_account_access', 'user_id' => $user->id, 'email' => $user->email,
        ], 'staff-account:'.$user->id);
    }

    public function assertReady(array $payload, string $recipient): User
    {
        $user = User::find($payload['user_id'] ?? null);
        if (! $user || $user->statut !== 'actif' || ! $user->must_change_password
            || $user->email !== ($payload['email'] ?? null)
            || ! Employe::where('user_id', $user->id)->exists()
            || app(WhatsAppEnvironmentGuard::class)->normalize((string) $user->telephone) !== app(WhatsAppEnvironmentGuard::class)->normalize($recipient)) {
            throw new \RuntimeException('staff_account_changed');
        }
        app(WhatsAppTemplateResolver::class)->assertApproved($this->template());

        return $user;
    }

    public function payload(array $payload, string $recipient): array
    {
        $user = $this->assertReady($payload, $recipient);
        $panel = Filament::getPanel('admin');
        $token = Password::broker($panel->getAuthPasswordBroker())->createToken($user);

        return app(WhatsAppTemplateResolver::class)->resolve($this->template(), [
            'recipient_name' => $user->name, 'login' => $user->email,
            'activation_url' => $panel->getResetPasswordUrl($token, $user),
        ]);
    }
}
