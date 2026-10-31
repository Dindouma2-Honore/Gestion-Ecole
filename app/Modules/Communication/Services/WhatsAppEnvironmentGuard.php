<?php

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Exceptions\WhatsAppTestRecipientNotAllowedException;

class WhatsAppEnvironmentGuard
{
    public function normalize(string $number): string
    {
        $number = preg_replace('/[\s().-]+/', '', $number);
        if (str_starts_with($number, '00')) {
            $number = '+'.substr($number, 2);
        }
        if (preg_match('/^6[0-9]{8}$/D', $number)) {
            $number = '+237'.$number;
        }
        if (! preg_match('/^\+?[1-9][0-9]{7,14}$/D', $number)) {
            throw new WhatsAppTestRecipientNotAllowedException('invalid_international_number');
        }

        return ltrim($number, '+');
    }

    public function check(string $recipient, bool $simulation = false): string
    {
        if (app()->environment('production')) {
            throw new WhatsAppTestRecipientNotAllowedException('test_only');
        }
        if (! config('whatsapp.enabled')) {
            throw new WhatsAppTestRecipientNotAllowedException('disabled');
        }
        $actual = $this->normalize($recipient);
        if ($simulation && config('whatsapp.override')) {
            if (! app()->environment(['local', 'testing'])) {
                throw new WhatsAppTestRecipientNotAllowedException('override_forbidden');
            }
            $actual = $this->normalize(config('whatsapp.override'));
        }
        $allowed = array_map($this->normalize(...), config('whatsapp.allowlist', []));
        if (config('whatsapp.mode') === 'test' && ! in_array($actual, $allowed, true)) {
            throw new WhatsAppTestRecipientNotAllowedException('recipient_not_allowed');
        }

        return $actual;
    }

    public function credentials(): void
    {
        foreach (['token', 'phone_id', 'waba_id', 'version'] as $key) {
            if (! config('whatsapp.'.$key)) {
                throw new \RuntimeException('missing_'.$key);
            }
        }
        if (! preg_match('/^v[0-9]+\.0$/D', config('whatsapp.version')) || ! ctype_digit((string) config('whatsapp.phone_id'))) {
            throw new \RuntimeException('invalid_provider_configuration');
        }
    }
}
