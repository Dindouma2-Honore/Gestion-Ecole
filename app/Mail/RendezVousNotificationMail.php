<?php

namespace App\Mail;

use App\Modules\Communication\Models\RendezVous;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RendezVousNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RendezVous $rendezVous
    ) {}

    public function envelope(): Envelope
    {
        $email = $this->rendezVous->parent?->email;

        return new Envelope(
            to: array_filter([$email]),
            subject: 'Confirmation & Informations de Rendez-vous - Ambassadors Complex',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rendez-vous-notification',
        );
    }
}
