<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Contracts\NotificationServiceContract;
use App\Modules\Communication\Models\FileAttenteNotification;
use App\Modules\Communication\Models\NotificationEnvoyee;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService implements NotificationServiceContract
{
    public function __construct(
        private ?ParametrageServiceContract $parametrage = null
    ) {}

    public function envoyer(string $canal, string $code, ?object $destinataire, array $donnees = []): object
    {
        if (! $destinataire) {
            Log::warning("Notification '{$code}' non envoyée : destinataire absent");

            return (object) ['statut' => 'echec', 'raison' => 'destinataire_absent'];
        }

        $contact = $this->resoudreContact($destinataire, $canal);
        $contenu = $this->resoudreContenuTemplate($canal, $code, $donnees);

        $destId = method_exists($destinataire, 'getKey') ? $destinataire->getKey() : ($destinataire->id ?? 0);

        $notification = NotificationEnvoyee::create([
            'canal' => $canal,
            'code_template' => $code,
            'destinataire_type' => get_class($destinataire),
            'destinataire_id' => $destId,
            'destinataire_contact' => $contact,
            'contenu_final' => $contenu,
            'statut' => 'en_attente',
        ]);

        FileAttenteNotification::create([
            'notification_id' => $notification->id,
            'priorite' => 5,
            'prochaine_tentative' => now(),
        ]);

        return $notification;
    }

    public function envoyerImmediat(string $canal, string $code, ?object $destinataire, array $donnees = []): bool
    {
        $notification = $this->envoyer($canal, $code, $destinataire, $donnees);

        if (isset($notification->statut) && $notification->statut === 'echec') {
            return false;
        }

        return $this->tenterEnvoi($notification);
    }

    public function traiterFileAttente(): void
    {
        $enAttente = FileAttenteNotification::where('prochaine_tentative', '<=', now())
            ->orderBy('priorite')
            ->with('notification')
            ->limit(50)
            ->get();

        foreach ($enAttente as $item) {
            if (! $item->notification) {
                $item->delete();
                continue;
            }

            $succes = $this->tenterEnvoi($item->notification);

            if ($succes) {
                $item->delete();
            } else {
                $item->notification->increment('tentatives');

                if ($item->notification->tentatives >= 5) {
                    $item->notification->update(['statut' => 'echec']);
                    $item->delete();
                } else {
                    $item->update([
                        'prochaine_tentative' => now()->addMinutes(2 ** $item->notification->tentatives),
                    ]);
                }
            }
        }
    }

    private function tenterEnvoi(object $notification): bool
    {
        try {
            match ($notification->canal) {
                'whatsapp' => $this->envoyerWhatsapp($notification),
                'sms' => $this->envoyerSms($notification),
                'email' => $this->envoyerEmail($notification),
                'in_app' => $this->creerNotificationInApp($notification),
                default => throw new \InvalidArgumentException("Canal inconnu : {$notification->canal}"),
            };

            if ($notification instanceof Model) {
                $notification->update(['statut' => 'envoyee', 'envoyee_le' => now()]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Erreur envoi notification #{$notification->id} : ".$e->getMessage());
            if ($notification instanceof Model) {
                $notification->update(['erreur_message' => $e->getMessage()]);
            }

            return false;
        }
    }

    private function resoudreContact(object $destinataire, string $canal): string
    {
        $destId = method_exists($destinataire, 'getKey') ? $destinataire->getKey() : ($destinataire->id ?? 0);

        return match ($canal) {
            'whatsapp', 'sms' => (string) ($destinataire->telephone ?? $destinataire->contact ?? ''),
            'email' => (string) ($destinataire->email ?? ''),
            'in_app' => (string) $destId,
            default => '',
        };
    }

    private function resoudreContenuTemplate(string $canal, string $code, array $donnees): string
    {
        if ($this->parametrage) {
            try {
                return $this->parametrage->getTemplateNotification($canal, $code, $donnees);
            } catch (\Throwable $e) {
                // Fallback si template absent dans parametrage
            }
        }

        $donneesStr = collect($donnees)->map(fn ($val, $key) => "{$key}: {$val}")->implode(', ');
        return "[Notification {$code}] {$donneesStr}";
    }

    private function envoyerWhatsapp(object $notification): void
    {
        $token = config('services.whatsapp.token');
        $phoneId = config('services.whatsapp.phone_id');
        $apiVersion = config('services.whatsapp.api_version', 'v25.0');

        if (! $token || ! $phoneId) {
            throw new \RuntimeException('WhatsApp API non configurée.');
        }

        $recipient = preg_replace('/\D+/', '', (string) $notification->destinataire_contact);

        if (! is_string($recipient) || $recipient === '') {
            throw new \InvalidArgumentException('Numéro WhatsApp destinataire invalide.');
        }

        $response = Http::withToken($token)
            ->post("https://graph.facebook.com/{$apiVersion}/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'text',
                'text' => ['body' => $notification->contenu_final],
            ]);

        Log::info('Réponse API WhatsApp', [
            'status' => $response->status(),
            'recipient' => $recipient,
            'response' => $response->json() ?? $response->body(),
        ]);

        if (! $response->successful()) {
            throw new \Exception('Échec envoi WhatsApp : '.$response->body());
        }
    }

    private function envoyerSms(object $notification): void
    {
        Log::info("Envoi SMS à {$notification->destinataire_contact} : {$notification->contenu_final}");
    }

    private function envoyerEmail(object $notification): void
    {
        Log::info("Envoi Email à {$notification->destinataire_contact} : {$notification->contenu_final}");
    }

    private function creerNotificationInApp(object $notification): void
    {
        Log::info("Notification In-App créée pour User #{$notification->destinataire_id} : {$notification->contenu_final}");
    }

    public function marquerLue(int $notificationId): void
    {
        NotificationEnvoyee::where('id', $notificationId)->update([
            'statut' => 'lue',
            'accuse_reception' => true,
        ]);
    }

    public function getHistorique(object $destinataire): Collection
    {
        $destId = method_exists($destinataire, 'getKey') ? $destinataire->getKey() : ($destinataire->id ?? 0);

        return NotificationEnvoyee::where('destinataire_type', get_class($destinataire))
            ->where('destinataire_id', $destId)
            ->orderByDesc('created_at')
            ->get();
    }
}
