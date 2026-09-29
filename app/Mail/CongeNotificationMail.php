<?php

namespace App\Mail;

use App\Modules\RH\Models\Conge;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CongeNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Conge $conge
    ) {}

    public function envelope(): Envelope
    {
        $email = $this->conge->employe?->email ?? $this->conge->employe?->user?->email;

        return new Envelope(
            to: array_filter([$email]),
            subject: 'Notification relative à votre demande de congé - Ambassadors Complex',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.conge-notification',
        );
    }
}
