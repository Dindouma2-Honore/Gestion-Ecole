<?php

declare(strict_types=1);

namespace App\Modules\Socle\Notifications;

use App\Modules\Socle\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentExpirationProche extends Notification
{
    use Queueable;

    public function __construct(private readonly Document $document) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Document arrivant à expiration')
            ->greeting('Bonjour,')
            ->line("Le document « {$this->document->nom} » arrive bientôt à expiration.")
            ->line('Date d’expiration : '.$this->document->date_expiration?->format('d/m/Y'))
            ->action('Consulter les documents', url('/admin/documents'));
    }
}
