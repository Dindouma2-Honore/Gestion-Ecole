<?php

declare(strict_types=1);

namespace App\Modules\Socle\Notifications;

use App\Modules\Socle\Models\Tache;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TacheEnRetard extends Notification
{
    use Queueable;

    public function __construct(private readonly Tache $tache) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tâche en retard')
            ->greeting('Bonjour,')
            ->line("La tâche « {$this->tache->titre} » a dépassé son échéance.")
            ->line('Échéance : '.$this->tache->echeance?->format('d/m/Y H:i'))
            ->action('Consulter les tâches', url('/admin/taches'));
    }
}
