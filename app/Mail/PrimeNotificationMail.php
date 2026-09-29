<?php

namespace App\Mail;

use App\Modules\RH\Models\Prime;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PrimeNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Prime $prime
    ) {}

    public function envelope(): Envelope
    {
        $email = $this->prime->employe?->email ?? $this->prime->employe?->user?->email;

        return new Envelope(
            to: array_filter([$email]),
            subject: 'Attribution de Prime & Gratification - Ambassadors Complex',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.prime-notification',
        );
    }
}
