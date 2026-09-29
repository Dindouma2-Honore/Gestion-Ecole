<?php

namespace App\Mail;

use App\Modules\RH\Models\Enseignant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnseignantCreeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Enseignant $enseignant
    ) {}

    public function envelope(): Envelope
    {
        $email = $this->enseignant->employe?->email ?? $this->enseignant->employe?->user?->email;

        return new Envelope(
            to: array_filter([$email]),
            subject: 'Bienvenue au sein du corps enseignant - Ambassadors Complex',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.enseignant-cree',
        );
    }
}
