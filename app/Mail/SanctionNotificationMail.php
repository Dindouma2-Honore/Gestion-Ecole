<?php

namespace App\Mail;

use App\Modules\RH\Models\SanctionPersonnel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SanctionNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SanctionPersonnel $sanction
    ) {}

    public function envelope(): Envelope
    {
        $email = $this->sanction->employe?->email ?? $this->sanction->employe?->user?->email;

        return new Envelope(
            to: array_filter([$email]),
            subject: 'Notification de sanction disciplinaire - Ambassadors Complex',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sanction-notification',
        );
    }
}
